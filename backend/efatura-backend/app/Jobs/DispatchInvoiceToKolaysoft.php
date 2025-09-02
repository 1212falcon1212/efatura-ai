<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Invoice;
use App\Services\Kolaysoft\KolaysoftClient;
use App\Services\Credits\CreditService;
use Illuminate\Support\Str;
use App\Services\UBL\UblBuilder;
use App\Models\Customer;
use App\Models\WebhookSubscription;
use App\Models\WebhookDelivery;
use App\Models\DeadLetter;
use App\Services\Resilience\CircuitBreaker;

class DispatchInvoiceToKolaysoft implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public function backoff(): array
    {
        return [30, 60, 120, 300, 600];
    }

    public function __construct(public int $invoiceId)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $invoice = Invoice::with('customerRecord')->find($this->invoiceId);
        if (! $invoice || $invoice->status !== 'queued') {
            return;
        }

        $client = new KolaysoftClient();
        // ETTN yoksa oluştur
        if (empty($invoice->ettn)) {
            $invoice->ettn = (string) Str::uuid();
            $invoice->save();
        }
        // InputDocument: XML varsa onu gönder; yoksa metadata ve satır bilgileriyle zengin map
        // Organizasyon bazlı URN (GB/PK) ayarlarını oku
        $org = \App\Models\Organization::find($invoice->organization_id);
        $orgSettings = $org?->settings ?? [];
        $orgGbUrn = (string) ($orgSettings['e_invoice_gb_urn'] ?? '');
        $orgPkUrn = (string) ($orgSettings['e_invoice_pk_urn'] ?? '');

        $input = $invoice->xml ? [
            'documentUUID' => $invoice->ettn,
            'xmlContent' => $invoice->xml,
            'xml' => $invoice->xml,
            'sourceUrn' => (string) (
                $invoice->type === 'e_arsiv'
                    ? ($orgGbUrn !== '' ? $orgGbUrn : (config('kolaysoft.source_urn_earchive') ?? ''))
                    : ($orgPkUrn !== '' ? $orgPkUrn : (config('kolaysoft.source_urn') ?? ''))
            ),
        ] : [
            // Kolaysoft için belge UUID zorunlu
            'documentUUID' => $invoice->ettn,
            'customer' => $invoice->customerRecord?->toArray() ?? $invoice->customer ?? [],
            'items' => $invoice->items,
            'issueDate' => optional($invoice->issue_date)->format('Y-m-d'),
            'type' => $invoice->type,
            'localId' => 'INV-'.$invoice->id,
            'sourceUrn' => (string) (
                $invoice->type === 'e_arsiv'
                    ? ($orgGbUrn !== '' ? $orgGbUrn : (config('kolaysoft.source_urn_earchive') ?? ''))
                    : ($orgPkUrn !== '' ? $orgPkUrn : (config('kolaysoft.source_urn') ?? ''))
            ),
            'metadata' => $invoice->metadata ?? [],
        ];

        // Tür (e_fatura | e_arsiv) bilgisini her durumda gönder
        $input['type'] = $invoice->type;

        // Belge tarihi ve ID: Fatura ID'sini oluşturup hem input'a hem de XML builder'a geçirelim.
        $documentId = $invoice->document_id;
        if (empty($documentId) || !preg_match('/^[A-Z]{3}[0-9]{13}$/', (string) $documentId)) {
            $prefix = strtoupper((string) (config('kolaysoft.document_id_prefix', 'ABC')));
            $prefix = preg_replace('/[^A-Z]/', '', $prefix) ?: 'ABC';
            $prefix = substr($prefix, 0, 3);
            // 3 harf + yıl(4) + 9 haneli rasgele sayı
            $documentId = $prefix . date('Y') . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
        }
        $input['documentId'] = $documentId;
        $input['documentDate'] = optional($invoice->issue_date)->format('Y-m-d') ?: date('Y-m-d');


        // VKN/TCKN bilgileri
        $sourceId = (string) (config('kolaysoft.source_id') ?? '');
        if ($sourceId !== '') {
            $input['vknTckn'] = $sourceId;
            $input['senderVknTckn'] = $sourceId;
        }
        // Alıcı VKN/TCKN: modelden veya DB'den oku
        $receiver = (string) (data_get($invoice, 'customerRecord.tckn_vkn') ?? data_get($invoice, 'customer.tckn_vkn') ?? '');
        if ($receiver !== '') { $input['receiverVknTckn'] = $receiver; }

        // destinationUrn/email
        if ($invoice->type === 'e_arsiv') {
            $destEmail = data_get($invoice, 'customerRecord.email') ?? data_get($invoice, 'customer.email');
            if (!empty($destEmail)) {
                $input['destinationUrn'] = $destEmail;
                $input['email'] = $destEmail;
            }
        } elseif ($invoice->type === 'e_fatura') {
            // E-Fatura için alıcının alias/URN bilgisi zorunlu
            $destUrn = data_get($invoice, 'customerRecord.urn') ?? data_get($invoice, 'customer.urn');
            if (!empty($destUrn)) {
                $input['destinationUrn'] = $destUrn;
            }
        }

        // Eğer xml yoksa - UBL üret (Artık doğru document_id ile)
        if (empty($invoice->xml)) {
            $xml = app(UblBuilder::class)->buildInvoiceXML($invoice, $invoice->ettn, $documentId);
            $input['xmlContent'] = $xml;
            $input['xml'] = $xml;
        }

        // Önce XML doğrulaması
        $xmlToCheck = $input['xmlContent'] ?? $invoice->xml ?? '';
        if (!empty($xmlToCheck)) {
            $client->controlInvoiceXML($xmlToCheck);
        }

        if (config('kolaysoft.debug')) {
            logger()->info('Kolaysoft sendInvoice start', [
                'type' => $invoice->type,
                'documentUUID' => $input['documentUUID'] ?? null,
                'documentId' => $input['documentId'] ?? null,
                'documentDate' => $input['documentDate'] ?? null,
                'hasXML' => isset($input['xmlContent']) && $input['xmlContent'] !== '',
                'email' => $input['email'] ?? null,
            ]);
        }
        // Circuit breaker: Kolaysoft kanalı için anahtar
        $cb = app(CircuitBreaker::class);
        $cbKey = 'kolaysoft:sendInvoice';
        if (!$cb->allow($cbKey)) {
            $invoice->status = 'failed';
            $invoice->save();
            DeadLetter::create([
                'type' => 'invoice.send',
                'reference_id' => $invoice->id,
                'queue' => $this->queue ?? 'default',
                'error' => 'Circuit breaker open',
                'payload' => ['input' => $input],
            ]);
            return;
        }

        $result = $client->sendInvoice($input);
        if (config('kolaysoft.debug')) {
            logger()->info('Kolaysoft sendInvoice result', [ 'result' => $result ]);
        }

        // Sonuç normalizasyonu (Kolaysoft dönüşleri farklı şekillerde olabilir)
        $provider = $result['return'] ?? $result;
        $code = (string) ($provider['code'] ?? '');
        $explanation = (string) ($provider['explanation'] ?? '');
        $cause = $provider['cause'] ?? null;

        if ($code === '000') {
            $invoice->status = 'sent';
            $cb->recordSuccess($cbKey);
            // Sağlayıcı referanslarını metadata altına yaz
            $meta = $invoice->metadata ?? [];
            $meta['kolaysoft_response'] = $provider;
            $invoice->metadata = $meta;
            // Varsayılan referans alanı
            if (property_exists($invoice, 'kolaysoft_ref')) {
                $invoice->kolaysoft_ref = $provider['documentID'] ?? $provider['documentUuid'] ?? null;
            }
            $invoice->save();
            // Limit ve tüketim politikaları
            $creditsPerDoc = (int) (config('billing.doc_cost_credits.invoice') ?? 1);
            $creditService = app(CreditService::class);
            $wallet = \App\Models\CreditWallet::firstOrCreate(['organization_id' => $invoice->organization_id]);
            $limitAction = (string) ($wallet->limit_action ?? 'block');
            // Günlük/aylık limit kontrolü (doküman sayısı bazlı)
            $todayCount = \App\Models\Invoice::where('organization_id', $invoice->organization_id)->where('status','sent')->whereDate('updated_at', now()->toDateString())->count();
            if (!empty($wallet->doc_limit_daily) && $todayCount >= (int) $wallet->doc_limit_daily) {
                if ($limitAction === 'block') {
                    $invoice->status = 'failed';
                    $invoice->save();
                    DeadLetter::create(['type'=>'invoice.send','reference_id'=>$invoice->id,'queue'=>$this->queue ?? 'default','error'=>'daily_limit_exceeded','payload'=>['organization_id'=>$invoice->organization_id]]);
                    return;
                }
            }
            $monthCount = \App\Models\Invoice::where('organization_id', $invoice->organization_id)->where('status','sent')->whereBetween('updated_at', [now()->startOfMonth(), now()->endOfMonth()])->count();
            if (!empty($wallet->doc_limit_monthly) && $monthCount >= (int) $wallet->doc_limit_monthly) {
                if ($limitAction === 'block') {
                    $invoice->status = 'failed';
                    $invoice->save();
                    DeadLetter::create(['type'=>'invoice.send','reference_id'=>$invoice->id,'queue'=>$this->queue ?? 'default','error'=>'monthly_limit_exceeded','payload'=>['organization_id'=>$invoice->organization_id]]);
                    return;
                }
            }

            // Kontör düşümü: önce müşterinin kalan limiti var mı? limit_action=continue ise havuzdan devam edebilir
            if ($creditService->hasSufficientCustomerCredits($invoice->organization_id, $creditsPerDoc)) {
                $creditService->debitCustomerCreditsForInvoice($invoice, $creditsPerDoc);
                $creditService->debitPoolCreditsForInvoice($invoice);
            } else {
                if ($limitAction === 'continue') {
                    // müşteriden düşmeden sadece havuzdan düşerek devam
                    $creditService->debitPoolCreditsForInvoice($invoice);
                    // uyarı amaçlı DLQ yerine bilgi logu
                    logger()->warning('customer_credits_insufficient_continue', ['invoice_id'=>$invoice->id,'org'=>$invoice->organization_id]);
                } else {
                    $invoice->status = 'failed';
                    $invoice->save();
                    DeadLetter::create([
                        'type' => 'invoice.send',
                        'reference_id' => $invoice->id,
                        'queue' => $this->queue ?? 'default',
                        'error' => 'insufficient_customer_credits',
                        'payload' => ['organization_id' => $invoice->organization_id],
                    ]);
                    return;
                }
            }
            $cost = (float) config('billing.invoice_cost_try', 0.0);
            if ($cost > 0) {
                app(CreditService::class)->debitForInvoice($invoice, $cost, 'TRY');
            }

            // Webhook: invoice.sent
            $payload = [
                'id' => $invoice->id,
                'organization_id' => $invoice->organization_id,
                'status' => $invoice->status,
                'type' => $invoice->type,
                'ettn' => $invoice->ettn,
                'document_id' => $input['documentId'] ?? null,
                'document_date' => $input['documentDate'] ?? null,
                'customer' => $invoice->customer,
                'totals' => [
                    'items_count' => is_array($invoice->items) ? count($invoice->items) : 0,
                ],
                'provider' => $provider,
            ];
            $subs = WebhookSubscription::where('organization_id', $invoice->organization_id)
                ->whereJsonContains('events', 'invoice.sent')
                ->get();
            foreach ($subs as $sub) {
                $delivery = WebhookDelivery::create([
                    'organization_id' => $invoice->organization_id,
                    'webhook_subscription_id' => $sub->id,
                    'event' => 'invoice.sent',
                    'payload' => $payload,
                    'status' => 'pending',
                    'attempt_count' => 0,
                ]);
                \App\Jobs\SendWebhookDelivery::dispatch($delivery->id)->onQueue('default');
            }
            return;
        }

        // Başarısız: kullanıcı-dostu mesaj eşlemesi
        $friendly = (string) (config('kolaysoft_errors.codes.' . $code) ?? $explanation ?: 'İşlem başarısız');
        if (!$friendly || $friendly === $explanation) {
            $patterns = (array) config('kolaysoft_errors.patterns', []);
            $hay = ($explanation . ' ' . json_encode($cause));
            foreach ($patterns as $rx => $msg) {
                if (@preg_match($rx, $hay)) {
                    if (preg_match($rx, $hay)) { $friendly = (string) $msg; break; }
                }
            }
        }
        $meta = $invoice->metadata ?? [];
        $meta['last_error'] = [
            'code' => $code,
            'message' => $friendly,
            'cause' => $cause,
            'raw' => $provider,
        ];
        $invoice->metadata = $meta;

        // Son deneme mi? O halde failed, değilse retrying ve istisna fırlat
        if ($this->attempts() >= $this->tries) {
            $invoice->status = 'failed';
            $invoice->save();
            // DLQ kaydı
            DeadLetter::create([
                'type' => 'invoice.send',
                'reference_id' => $invoice->id,
                'queue' => $this->queue ?? 'default',
                'error' => $friendly,
                'payload' => ['input' => $input, 'provider' => $provider],
            ]);
            // Circuit breaker failure say
            $cb->recordFailure($cbKey);

            // Webhook: invoice.failed (son deneme)
            $payload = [
                'id' => $invoice->id,
                'organization_id' => $invoice->organization_id,
                'status' => $invoice->status,
                'type' => $invoice->type,
                'ettn' => $invoice->ettn,
                'error' => [
                    'code' => $code,
                    'message' => $friendly,
                    'cause' => $cause,
                ],
            ];
            $subs = WebhookSubscription::where('organization_id', $invoice->organization_id)
                ->whereJsonContains('events', 'invoice.failed')
                ->get();
            foreach ($subs as $sub) {
                $delivery = WebhookDelivery::create([
                    'organization_id' => $invoice->organization_id,
                    'webhook_subscription_id' => $sub->id,
                    'event' => 'invoice.failed',
                    'payload' => $payload,
                    'status' => 'pending',
                    'attempt_count' => 0,
                ]);
                \App\Jobs\SendWebhookDelivery::dispatch($delivery->id)->onQueue('default');
            }
            return;
        }

        $invoice->status = 'retrying';
        $invoice->save();
        // Failure sayımı artır (CB)
        $cb->recordFailure($cbKey);
        throw new \RuntimeException('Kolaysoft sendInvoice failed with code ' . $code . ' - ' . $friendly);
    }
}
