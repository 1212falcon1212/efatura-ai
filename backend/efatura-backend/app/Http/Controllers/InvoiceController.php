<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Customer;
use App\Jobs\DispatchInvoiceToKolaysoft;
use App\Services\Kolaysoft\KolaysoftClient;
use App\Models\AuditLog;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $org = $request->attributes->get('organization');
        $query = Invoice::where('organization_id', $org->id);

        // Basit filtreler
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }
        if ($ettn = $request->input('ettn')) {
            $query->where('ettn', 'like', "%$ettn%");
        }
        if ($q = $request->input('q')) {
            // MySQL JSON_EXTRACT ile müşteri adı arama
            $query->whereRaw("JSON_EXTRACT(customer, '$.name') LIKE ?", ['%'.$q.'%']);
        }

        $limit = min(200, max(1, (int) $request->input('page.limit', 50)));
        $after = $request->input('page.after');
        if ($after) {
            $query->where('id', '<', (int) $after);
        }

        // Sıralama (id, issue_date, status, grand_total)
        $sort = $request->input('sort');
        $order = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        switch ($sort) {
            case 'issue_date':
                $query->orderBy('issue_date', $order);
                break;
            case 'status':
                $query->orderBy('status', $order)->orderBy('id', 'desc');
                break;
            case 'grand_total':
                // JSON toplam tutara göre sırala (MySQL JSON_EXTRACT)
                $query->orderByRaw("JSON_EXTRACT(totals, '$.grandTotal.amount') ".$order.", id DESC");
                break;
            case 'id':
                $query->orderBy('id', $order);
                break;
            default:
                $query->orderBy('id', 'desc');
        }

        $invoices = $query->limit($limit + 1)->get();
        $nextCursor = null;
        if ($invoices->count() > $limit) {
            $nextCursor = (string) $invoices->last()->id;
            $invoices = $invoices->slice(0, $limit)->values();
        }

        $response = response()->json(['data' => $invoices]);
        if ($nextCursor) {
            $response->headers->set('X-Next-Cursor', $nextCursor);
        }
        return $response;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $org = $request->attributes->get('organization');

        $data = $request->validate([
            'external_id' => ['nullable','string'],
            'type' => ['nullable','in:e_fatura,e_arsiv'],
            'issue_date' => ['nullable','date'],
            'customer' => ['nullable','array'],
            'customer.id' => ['nullable','integer'],
            'customer.tckn_vkn' => ['nullable', 'string', 'max:11'],
            'customer.name' => ['nullable', 'string', 'max:255'],
            'customer.surname' => ['nullable', 'string', 'max:255'],
            'customer.email' => ['nullable', 'string', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:255'],
            'customer.city' => ['nullable', 'string', 'max:255'],
            'customer.district' => ['nullable', 'string', 'max:255'],
            'customer.tax_office' => ['nullable', 'string', 'max:255'],
            'customer.urn' => ['required_if:type,e_fatura', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.sku' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:255'],
            'items.*.vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'items.*.unit_price' => ['required', 'array'],
            'items.*.unit_price.amount' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price.currency' => ['required', 'string', 'max:3'],
            'xml' => ['nullable','string'],
            'ettn' => ['nullable','string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $customerData = $data['customer'] ?? null;
        $customerId = null;
        $customerJson = null;

        // Eğer bir müşteri ID'si geldiyse, o müşteriyi bul ve verilerini kullan.
        if (!empty($customerData['id'])) {
            $customer = Customer::where('organization_id', $org->id)->find($customerData['id']);
            if ($customer) {
                $customerId = $customer->id;
                $customerJson = $customer->toArray();
            }
        }
        // Eğer ID yoksa ama VKN/TCKN varsa, müşteri oluştur/güncelle.
        elseif ($customerData && !empty($customerData['tckn_vkn'])) {
            $customer = Customer::updateOrCreate(
                [
                    'organization_id' => $org->id,
                    'tckn_vkn' => $customerData['tckn_vkn'],
                ],
                [
                    'name' => $customerData['name'] ?? '',
                    'surname' => $customerData['surname'] ?? null,
                    'email' => $customerData['email'] ?? null,
                    'phone' => $customerData['phone'] ?? null,
                    'city' => $customerData['city'] ?? null,
                    'district' => $customerData['district'] ?? null,
                    'street_address' => $customerData['street_address'] ?? null,
                    'tax_office' => $customerData['tax_office'] ?? null,
                    'urn' => $customerData['urn'] ?? null,
                ]
            );
            $customerId = $customer->id;
            $customerJson = $customer->toArray();
        }

        // Ürün auto-upsert (SKU bazlı)
        $items = $data['items'] ?? [];
        $normalizedItems = [];
        foreach ($items as $it) {
            $sku = $it['sku'] ?? null;
            if ($sku) {
                $product = \App\Models\Product::firstOrCreate(
                    [
                        'organization_id' => $org->id,
                        'sku' => $sku,
                    ],
                    [
                        'name' => $it['name'] ?? $sku,
                        'unit' => $it['unit'] ?? 'C62',
                        'vat_rate' => $it['vat_rate'] ?? 0,
                        'unit_price' => $it['unit_price']['amount'] ?? 0,
                        'currency' => $it['unit_price']['currency'] ?? 'TRY',
                    ]
                );
                // Ürün adı/fiyatı değişmişse güncelle (opsiyonel senkron)
                $updated = false;
                if (($it['name'] ?? null) && $product->name !== $it['name']) { $product->name = $it['name']; $updated = true; }
                if (($it['unit'] ?? null) && $product->unit !== $it['unit']) { $product->unit = $it['unit']; $updated = true; }
                if (isset($it['vat_rate']) && (float)$product->vat_rate !== (float)$it['vat_rate']) { $product->vat_rate = $it['vat_rate']; $updated = true; }
                $priceAmount = $it['unit_price']['amount'] ?? null;
                $priceCurr = $it['unit_price']['currency'] ?? null;
                if ($priceAmount !== null && (float)$product->unit_price !== (float)$priceAmount) { $product->unit_price = $priceAmount; $updated = true; }
                if ($priceCurr && $product->currency !== $priceCurr) { $product->currency = $priceCurr; $updated = true; }
                if ($updated) { $product->save(); }

                $it['product_id'] = $product->id;
            }
            $normalizedItems[] = $it;
        }

        $invoice = Invoice::create([
            'organization_id' => $org->id,
            'customer_id' => $customerId,
            'external_id' => $data['external_id'] ?? null,
            'type' => $data['type'] ?? 'e_fatura',
            'issue_date' => $data['issue_date'] ?? null,
            'customer' => $customerJson ?: ($customerData ?: []),
            'items' => $normalizedItems,
            'xml' => $data['xml'] ?? null,
            'ettn' => $data['ettn'] ?? null,
            'status' => 'queued',
            'metadata' => $data['metadata'] ?? [],
        ]);

        // Kalemleri yaz ve toplamları hesapla
        $subtotal = 0.0;
        $vatTotal = 0.0;
        $currency = null;

        if (!empty($data['xml'])) {
            // XML verildiyse satır yazımı atlanabilir; totals bilinmiyorsa hesaplanmayacak
        } else {
        foreach ($data['items'] as $item) {
            $name = (string) ($item['name'] ?? 'Satır');
            $sku = $item['sku'] ?? null;
            $quantity = (float) ($item['quantity'] ?? 1);
            $unit = (string) ($item['unit'] ?? 'Adet');
            $vatRate = (float) ($item['vat_rate'] ?? 0);
            $priceAmount = (float) ($item['unit_price']['amount'] ?? 0);
            $lineCurrency = (string) ($item['unit_price']['currency'] ?? 'TRY');
            $currency = $currency ?? $lineCurrency;

            $lineNet = round($quantity * $priceAmount, 2);
            $lineVat = round($lineNet * $vatRate / 100, 2);
            $lineGross = round($lineNet + $lineVat, 2);

            $subtotal += $lineNet;
            $vatTotal += $lineVat;

            $productId = null;
            if ($sku) {
                $productId = optional(Product::where('organization_id', $org->id)->where('sku', $sku)->first())->id;
            }

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $productId,
                'sku' => $sku,
                'name' => $name,
                'quantity' => $quantity,
                'unit' => $unit,
                'vat_rate' => $vatRate,
                'unit_price' => $priceAmount,
                'currency' => $lineCurrency,
                'total' => $lineGross,
            ]);
        }

        $grandTotal = round($subtotal + $vatTotal, 2);
        $totals = [
            'subtotal' => ['amount' => round($subtotal, 2), 'currency' => $currency ?? 'TRY'],
            'vatTotal' => ['amount' => round($vatTotal, 2), 'currency' => $currency ?? 'TRY'],
            'grandTotal' => ['amount' => $grandTotal, 'currency' => $currency ?? 'TRY'],
        ];
        $invoice->update(['totals' => $totals]);
        }

        // Audit log
        AuditLog::create([
            'organization_id' => $org->id,
            'user_id' => null,
            'action' => 'invoice.create',
            'entity_type' => 'invoice',
            'entity_id' => $invoice->id,
            'context' => ['external_id' => $invoice->external_id, 'type' => $invoice->type],
        ]);

        // Kuyruğa Kolaysoft gönderim job'u ekle
        DispatchInvoiceToKolaysoft::dispatch($invoice->id)->afterCommit();

        return response()->json($invoice->fresh(), 202);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $org = $request->attributes->get('organization');
        $invoice = Invoice::where('organization_id', $org->id)->findOrFail($id);
        return response()->json($invoice);
    }

    public function cancel(Request $request, string $id)
    {
        $org = $request->attributes->get('organization');
        $invoice = Invoice::where('organization_id', $org->id)->findOrFail($id);
        if (! in_array($invoice->status, ['queued','processing'])) {
            return response()->json(['code' => 'conflict', 'message' => 'Invoice not cancelable in current status'], 409);
        }
        // Kolaysoft iptal (varsa ETTN ile)
        $data = $request->validate([
            'cancelReason' => ['nullable','string'],
            'cancelDate' => ['nullable','date'],
        ]);
        if (!empty($invoice->ettn)) {
            $reason = $data['cancelReason'] ?? 'Customer request';
            $date = ($data['cancelDate'] ?? now()->toDateString());
            app(KolaysoftClient::class)->cancelInvoice($invoice->ettn, $reason, $date);
        }
        $invoice->update(['status' => 'canceled']);
        AuditLog::create([
            'organization_id' => $org->id,
            'user_id' => null,
            'action' => 'invoice.cancel',
            'entity_type' => 'invoice',
            'entity_id' => $invoice->id,
            'context' => ['reason' => $data['cancelReason'] ?? null],
        ]);
        return response()->json($invoice);
    }

    public function retry(Request $request, string $id)
    {
        $org = $request->attributes->get('organization');
        $invoice = Invoice::where('organization_id', $org->id)->findOrFail($id);
        if ($invoice->status !== 'failed') {
            return response()->json(['code' => 'conflict', 'message' => 'Retry allowed only for failed invoices'], 409);
        }
        $invoice->update(['status' => 'queued']);
        AuditLog::create([
            'organization_id' => $org->id,
            'user_id' => null,
            'action' => 'invoice.retry',
            'entity_type' => 'invoice',
            'entity_id' => $invoice->id,
            'context' => [],
        ]);
        \App\Jobs\DispatchInvoiceToKolaysoft::dispatch($invoice->id)->afterCommit();
        return response()->json($invoice->fresh(), 202);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
