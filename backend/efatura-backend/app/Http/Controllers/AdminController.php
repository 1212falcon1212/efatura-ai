<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Coupon;
use App\Models\CreditWallet;
use App\Models\CreditTransaction;
use App\Models\DeadLetter;
use App\Models\WebhookDelivery;
use App\Models\Invoice;

class AdminController extends Controller
{
    public function organizations(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $limit = min(200, max(1, (int) $request->input('per_page', 50)));
        $query = Organization::query()->orderByDesc('id');
        if ($q !== '') {
            $query->where('name', 'like', '%'.$q.'%');
        }
        $items = $query->paginate($limit);
        return response()->json($items);
    }

    public function adjustCredits(Request $request)
    {
        $data = $request->validate([
            'organization_id' => ['required','integer'],
            'amount' => ['required','numeric'],
            'reason' => ['nullable','string','max:255'],
        ]);
        $wallet = CreditWallet::firstOrCreate(['organization_id' => (int) $data['organization_id']]);
        $wallet->balance = (float) ($wallet->balance ?? 0) + (float) $data['amount'];
        $wallet->save();
        CreditTransaction::create([
            'credit_wallet_id' => $wallet->id,
            'type' => ((float)$data['amount'] >= 0 ? 'credit' : 'debit'),
            'amount' => abs((float) $data['amount']),
            'currency' => 'CREDITS',
            'metadata' => ['reason' => $data['reason'] ?? 'manual_adjustment'],
        ]);
        return response()->json(['ok' => true, 'balance' => (float) $wallet->balance]);
    }

    public function deadLetters(Request $request)
    {
        $q = DeadLetter::orderByDesc('id');
        $limit = min(200, max(1, (int) $request->input('page.limit', 50)));
        $after = $request->input('page.after');
        if ($after) { $q->where('id', '<', (int) $after); }
        $items = $q->limit($limit + 1)->get();
        $next = null; if ($items->count() > $limit) { $next = (string) $items->last()->id; $items = $items->slice(0, $limit)->values(); }
        $resp = response()->json(['data' => $items]); if ($next) { $resp->headers->set('X-Next-Cursor', $next); }
        return $resp;
    }

    public function deadLetterDelete(Request $request, int $id)
    {
        $dl = DeadLetter::findOrFail($id);
        $dl->delete();
        return response()->json(['ok' => true]);
    }

    public function deadLetterRetry(Request $request, int $id)
    {
        $dl = DeadLetter::findOrFail($id);
        $type = $dl->type;
        $ref = (int) $dl->reference_id;
        switch ($type) {
            case 'invoice.send':
                if ($ref > 0) { \App\Jobs\DispatchInvoiceToKolaysoft::dispatch($ref); $dl->delete(); return response()->json(['ok' => true]); }
                break;
            case 'voucher.send':
                // Tekrar gönderim için invoice id bulunamadı; sadece silmeden hata dön
                return response()->json(['message' => 'retry_not_supported_for_voucher'], 422);
            case 'despatch.send':
                return response()->json(['message' => 'retry_not_supported_for_despatch'], 422);
            case 'webhook.delivery':
                if ($ref > 0) { \App\Jobs\SendWebhookDelivery::dispatch($ref); $dl->delete(); return response()->json(['ok' => true]); }
                break;
        }
        return response()->json(['message' => 'cannot_retry'], 422);
    }

    // Toplu: failed webhook teslimatlarını yeniden sıraya al
    public function replayWebhooksBulk(Request $request)
    {
        $data = $request->validate([
            'event' => ['sometimes','string','max:128'],
            'days' => ['sometimes','integer','min:1','max:90'],
            'limit' => ['sometimes','integer','min:1','max:1000'],
        ]);
        $days = (int) ($data['days'] ?? 7);
        $limit = (int) ($data['limit'] ?? 200);
        $q = WebhookDelivery::where('status','failed')->where('created_at','>=', now()->subDays($days));
        if (!empty($data['event'])) { $q->where('event', $data['event']); }
        $ids = $q->orderBy('id')->limit($limit)->pluck('id');
        foreach ($ids as $id) { \App\Jobs\SendWebhookDelivery::dispatch((int) $id); }
        return response()->json(['queued' => $ids->count()]);
    }

    // Toplu: başarısız faturaları yeniden sıraya al
    public function requeueInvoicesBulk(Request $request)
    {
        $data = $request->validate([
            'days' => ['sometimes','integer','min:1','max:90'],
            'limit' => ['sometimes','integer','min:1','max:1000'],
        ]);
        $days = (int) ($data['days'] ?? 7);
        $limit = (int) ($data['limit'] ?? 200);
        $items = Invoice::where('status','failed')->where('updated_at','>=', now()->subDays($days))->orderBy('id')->limit($limit)->get(['id']);
        foreach ($items as $inv) {
            Invoice::where('id', $inv->id)->update(['status' => 'queued']);
            \App\Jobs\DispatchInvoiceToKolaysoft::dispatch((int) $inv->id);
        }
        return response()->json(['queued' => $items->count()]);
    }

    public function plansIndex(Request $request)
    {
        $items = Plan::orderBy('price_try')->get();
        return response()->json(['data' => $items]);
    }

    public function plansCreate(Request $request)
    {
        $data = $request->validate([
            'code' => ['required','string','max:64','unique:plans,code'],
            'name' => ['required','string','max:255'],
            'price_try' => ['required','integer','min:0'],
            'limits' => ['nullable','array'],
            'active' => ['nullable','boolean'],
        ]);
        $plan = Plan::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'price_try' => $data['price_try'],
            'limits' => $data['limits'] ?? [],
            'active' => (bool) ($data['active'] ?? true),
        ]);
        return response()->json($plan, 201);
    }

    public function plansUpdate(Request $request, int $id)
    {
        $plan = Plan::findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes','string','max:255'],
            'price_try' => ['sometimes','integer','min:0'],
            'limits' => ['sometimes','array'],
            'active' => ['sometimes','boolean'],
        ]);
        $plan->fill($data);
        $plan->save();
        return response()->json($plan);
    }

    public function coupons(Request $request)
    {
        $org = $request->attributes->get('organization');
        $q = Coupon::where('organization_id', $org->id)->orderByDesc('id');
        $limit = min(200, max(1, (int) $request->input('page.limit', 50)));
        $after = $request->input('page.after');
        if ($after) { $q->where('id', '<', (int) $after); }
        $items = $q->limit($limit + 1)->get();
        $next = null; if ($items->count() > $limit) { $next = (string) $items->last()->id; $items = $items->slice(0, $limit)->values(); }
        $resp = response()->json(['data' => $items]); if ($next) { $resp->headers->set('X-Next-Cursor', $next); }
        return $resp;
    }

    public function couponsCreate(Request $request)
    {
        $org = $request->attributes->get('organization');
        $data = $request->validate([
            'code' => ['required','string','max:64','unique:coupons,code'],
            'percent_off' => ['required','integer','min:1','max:100'],
            'active' => ['sometimes','boolean'],
        ]);
        $coupon = Coupon::create([
            'organization_id' => $org->id,
            'code' => $data['code'],
            'percent_off' => $data['percent_off'],
            'active' => (bool) ($data['active'] ?? true),
        ]);
        return response()->json($coupon, 201);
    }

    public function couponsDelete(Request $request, int $id)
    {
        $org = $request->attributes->get('organization');
        $coupon = Coupon::where('organization_id', $org->id)->where('id', $id)->firstOrFail();
        $coupon->delete();
        return response()->json(['ok' => true]);
    }
}


