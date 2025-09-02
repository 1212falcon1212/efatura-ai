<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{CreditWallet, CreditTransaction};
use App\Services\Kolaysoft\KolaysoftClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use App\Models\{CreditTransaction as WalletTransaction, CreditWallet as Wallet, Payment};
use App\Models\CreditReservation;

class CreditController extends Controller
{
    public function wallet(Request $request)
    {
        $org = $request->attributes->get('organization');
        $wallet = CreditWallet::firstOrCreate(['organization_id' => $org->id]);
        return response()->json($wallet);
    }

    public function transactions(Request $request)
    {
        $org = $request->attributes->get('organization');
        $wallet = CreditWallet::firstOrCreate(['organization_id' => $org->id]);
        $query = CreditTransaction::where('credit_wallet_id', $wallet->id)->orderByDesc('id');
        $limit = min(200, max(1, (int) $request->input('page.limit', 50)));
        $after = $request->input('page.after');
        if ($after) {
            $query->where('id', '<', (int) $after);
        }
        $items = $query->limit($limit + 1)->get();
        $next = null;
        if ($items->count() > $limit) {
            $next = (string) $items->last()->id;
            $items = $items->slice(0, $limit)->values();
        }
        $resp = response()->json(['data' => $items]);
        if ($next) { $resp->headers->set('X-Next-Cursor', $next); }
        return $resp;
    }

    public function providerBalance(Request $request)
    {
        // Kolaysoft kontör/bakiye sorgusu (mock veya gerçek)
        try {
            $res = app(KolaysoftClient::class)->getCustomerCreditCount();
            return response()->json($res);
        } catch (\Throwable $e) {
            \Log::error('providerBalance upstream_error', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'code' => 'upstream_error',
                'message' => 'Sağlayıcıya bağlanırken bir hata oluştu',
            ], 502);
        }
    }

    // Owner için havuz özeti (DB + rezervasyon + sağlayıcı canlı)
    public function summary(Request $request)
    {
        $ownerOrgId = (int) config('billing.owner_organization_id');
        $pool = Wallet::firstOrCreate(['organization_id' => $ownerOrgId]);
        $reserved = (int) CreditReservation::where('status','reserved')->sum('credits');
        $available = (float) ($pool->balance ?? 0) - (float) $reserved;
        $customersTotal = (float) Wallet::where('organization_id', '!=', $ownerOrgId)->sum('balance');
        $provider = null;
        try { $provider = app(KolaysoftClient::class)->getCustomerCreditCount(); } catch (\Throwable $e) { $provider = ['success' => false, 'message' => 'provider_unavailable']; }
        return response()->json([
            'pool' => [
                'balance' => (float) ($pool->balance ?? 0),
                'reserved' => (int) $reserved,
                'available' => (float) $available,
            ],
            'customers_total' => (float) $customersTotal,
            'provider' => $provider,
        ]);
    }

    // Owner analitiği: son 7 gün tüketim ve en çok tüketen organizasyonlar
    public function analytics(Request $request)
    {
        $since = now()->subDays(7)->startOfDay();
        $consumed = \App\Models\CreditTransaction::query()
            ->where('type', 'debit')
            ->where('currency', 'CREDITS')
            ->where('created_at', '>=', $since)
            ->get(['credit_wallet_id','amount','metadata','created_at']);

        // Günlük toplam
        $byDay = [];
        foreach ($consumed as $tx) {
            $day = $tx->created_at->format('Y-m-d');
            $byDay[$day] = ($byDay[$day] ?? 0) + (float) $tx->amount;
        }
        ksort($byDay);

        // Top orglar
        $walletIds = $consumed->pluck('credit_wallet_id')->unique()->values();
        $walletOrg = Wallet::whereIn('id', $walletIds)->pluck('organization_id','id');
        $byOrg = [];
        foreach ($consumed as $tx) {
            $orgId = (int) ($walletOrg[$tx->credit_wallet_id] ?? 0);
            if ($orgId) {
                $byOrg[$orgId] = ($byOrg[$orgId] ?? 0) + (float) $tx->amount;
            }
        }
        arsort($byOrg);
        $top = [];
        foreach (array_slice($byOrg, 0, 10, true) as $orgId => $amt) {
            $org = \App\Models\Organization::find($orgId);
            $top[] = ['organization_id' => $orgId, 'organization_name' => $org->name ?? ('#'.$orgId), 'credits' => (float) $amt];
        }

        return response()->json([
            'usage_last_7_days' => $byDay,
            'top_organizations' => $top,
        ]);
    }

    // Süresi geçmiş rezervasyonları iptal et
    public function cleanupReservations(Request $request)
    {
        $count = CreditReservation::where('status','reserved')->where('expires_at','<', now())->update(['status' => 'cancelled']);
        return response()->json(['cancelled' => $count]);
    }

    public function updateSettings(Request $request)
    {
        $org = $request->attributes->get('organization');
        $data = $request->validate([
            'low_balance_threshold' => ['nullable','numeric','min:0'],
            'auto_topup_enabled' => ['nullable','boolean'],
            'doc_limit_daily' => ['nullable','integer','min:0'],
            'doc_limit_monthly' => ['nullable','integer','min:0'],
            'limit_action' => ['nullable','in:block,continue'],
            'auto_purchase_enabled' => ['nullable','boolean'],
            'auto_purchase_package' => ['nullable','string','max:32'],
        ]);
        $wallet = CreditWallet::firstOrCreate(['organization_id' => $org->id]);
        if (array_key_exists('low_balance_threshold', $data)) {
            $wallet->low_balance_threshold = $data['low_balance_threshold'];
        }
        if (array_key_exists('auto_topup_enabled', $data)) {
            $wallet->auto_topup_enabled = $data['auto_topup_enabled'];
        }
        foreach (['doc_limit_daily','doc_limit_monthly','limit_action','auto_purchase_enabled','auto_purchase_package'] as $k) {
            if (array_key_exists($k, $data)) { $wallet->{$k} = $data[$k]; }
        }
        $wallet->save();
        return response()->json($wallet);
    }

    public function purchase(Request $request)
    {
        $org = $request->attributes->get('organization');
        $data = $request->validate([
            'package_code' => ['required','string'],
        ]);
        $packages = config('billing.credit_packages');
        $pkg = collect($packages)->firstWhere('code', $data['package_code']);
        if (!$pkg) return response()->json(['message' => 'Geçersiz paket'], 422);

        // Ödeme gerektir: Payment oluştur ve Moka akışıyla tahsil et (basitleştirilmiş)
        $payment = Payment::create([
            'organization_id' => $org->id,
            'subscription_invoice_id' => null,
            'provider' => 'moka',
            'amount_try' => (float) $pkg['price_try'],
            'currency' => 'TRY',
            'status' => 'initiated',
            'request_json' => ['package' => $pkg['code']],
        ]);

        // Havuz ve müşteri cüzdanları
        $ownerOrgId = (int) config('billing.owner_organization_id');
        $ownerWallet = Wallet::firstOrCreate(['organization_id' => $ownerOrgId]);
        $userWallet = Wallet::firstOrCreate(['organization_id' => $org->id]);

        // Havuzda yeterli kontör var mı?
        if (($ownerWallet->balance ?? 0) < (float) $pkg['credits']) {
            return response()->json(['message' => 'Yetersiz havuz bakiyesi'], 409);
        }

        // Ödeme onaylandığında aktarım yapılacak; şimdilik doğrudan aktaralım (ödeme entegre edilince bağlanacak)
        DB::transaction(function () use ($ownerWallet, $userWallet, $pkg) {
            $ownerWallet->balance = (float) $ownerWallet->balance - (float) $pkg['credits'];
            $ownerWallet->save();
            WalletTransaction::create([
                'credit_wallet_id' => $ownerWallet->id,
                'type' => 'debit',
                'amount' => (float) $pkg['credits'],
                'currency' => 'CREDITS',
                'metadata' => ['reason' => 'transfer_to_customer', 'package' => $pkg['code']],
            ]);

            $userWallet->balance = (float) $userWallet->balance + (float) $pkg['credits'];
            $userWallet->save();
            WalletTransaction::create([
                'credit_wallet_id' => $userWallet->id,
                'type' => 'credit',
                'amount' => (float) $pkg['credits'],
                'currency' => 'CREDITS',
                'metadata' => ['reason' => 'package_topup', 'package' => $pkg['code']],
            ]);
        });

        return response()->json(['ok' => true, 'payment_id' => $payment->id, 'package' => $pkg['code']]);
    }
}
