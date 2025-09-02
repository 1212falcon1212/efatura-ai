<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SubscriptionInvoice;
use App\Models\Payment;
use App\Models\SavedCard;
use App\Services\Payments\MokaClient;
use App\Models\CreditReservation;

class PaymentController extends Controller
{
    public function createPayment(Request $request)
    {
        $org = $request->attributes->get('organization');
        $data = $request->validate([
            'subscription_invoice_id' => ['required', 'integer'],
            'card_holder_full_name' => ['required','string','max:128'],
            'card_number' => ['required','string','max:22'],
            'exp_month' => ['required','string','max:2'],
            'exp_year' => ['required','string','max:4'],
            'cvc' => ['required','string','max:4'],
            'use_3d' => ['sometimes','boolean'],
            'redirect_url' => ['required_if:use_3d,true','url'],
            // Optional buyer/customer information
            'buyer_full_name' => ['sometimes','string','max:128'],
            'buyer_email' => ['sometimes','email','max:255'],
            'buyer_gsm' => ['sometimes','string','max:32'],
            'buyer_address' => ['sometimes','string','max:512'],
            'customer_code' => ['sometimes','string','max:128'],
            'customer_first_name' => ['sometimes','string','max:128'],
            'customer_last_name' => ['sometimes','string','max:128'],
            'customer_email' => ['sometimes','email','max:255'],
            'customer_address' => ['sometimes','string','max:512'],
        ]);

        $invoice = SubscriptionInvoice::where('id', $data['subscription_invoice_id'])
            ->whereHas('subscription', fn($q) => $q->where('organization_id', $org->id))
            ->firstOrFail();

        $payment = Payment::create([
            'organization_id' => $org->id,
            'subscription_invoice_id' => $invoice->id,
            'provider' => 'moka',
            'amount_try' => $invoice->amount_try,
            'currency' => 'TRY',
            'status' => 'initiated',
            'request_json' => [],
        ]);

        $moka = MokaClient::fromConfig();
        $payload = [
            'CardHolderFullName' => $data['card_holder_full_name'],
            'CardNumber' => $data['card_number'],
            'ExpMonth' => $data['exp_month'],
            'ExpYear' => $data['exp_year'],
            'CvcNumber' => $data['cvc'],
            'Amount' => (float) number_format($invoice->amount_try, 2, '.', ''),
            'Currency' => 'TL',
            'InstallmentNumber' => 1,
            'ClientIP' => $request->ip(),
            'OtherTrxCode' => 'SUBINV-'.$invoice->id,
            'IsPoolPayment' => 0,
            'IsTokenized' => 0,
            'Software' => 'efatura.ai',
            'Description' => 'Abonelik ödemesi',
            'IsPreAuth' => 0,
        ];
        // Optional blocks
        $buyerInfo = [];
        if (!empty($data['buyer_full_name'])) $buyerInfo['BuyerFullName'] = $data['buyer_full_name'];
        if (!empty($data['buyer_email'])) $buyerInfo['BuyerEmail'] = $data['buyer_email'];
        if (!empty($data['buyer_gsm'])) $buyerInfo['BuyerGsmNumber'] = $data['buyer_gsm'];
        if (!empty($data['buyer_address'])) $buyerInfo['BuyerAddress'] = $data['buyer_address'];
        if (!empty($buyerInfo)) $payload['BuyerInformation'] = $buyerInfo;

        $custInfo = [];
        if (!empty($data['customer_code'])) $custInfo['CustomerCode'] = $data['customer_code'];
        if (!empty($data['customer_first_name'])) $custInfo['FirstName'] = $data['customer_first_name'];
        if (!empty($data['customer_last_name'])) $custInfo['LastName'] = $data['customer_last_name'];
        if (!empty($data['customer_email'])) $custInfo['Email'] = $data['customer_email'];
        if (!empty($data['customer_address'])) $custInfo['Address'] = $data['customer_address'];
        if (!empty($custInfo)) $payload['CustomerInformation'] = $custInfo;
        if (!empty($data['use_3d'])) {
            $payload['ReturnHash'] = 1;
            $payload['RedirectUrl'] = $data['redirect_url'];
            $payload['RedirectType'] = 0;
            $resp = $moka->chargeThreeD($payload);
        } else {
            $resp = $moka->charge($payload);
        }
        $json = $resp['json'] ?? [];
        $resultCode = $json['ResultCode'] ?? null;
        $resultMessage = $json['ResultMessage'] ?? null;

        $payment->request_json = $payload;
        $payment->response_json = $json;

        if (!empty($data['use_3d'])) {
            $payment->save();
            // ThreeD başlangıcında doğrudan capture olmaz; yönlendirme bilgisi döner
            return response()->json(['three_d' => true, 'response' => $json]);
        }

        if (($resp['status'] ?? false) && ($resultCode === 'Success')) {
            $payment->status = 'captured';
            $payment->provider_txn_id = $json['Data']['VirtualPosOrderId'] ?? null;
            // Kart token geldiyse sakla
            $token = $json['Data']['CardToken'] ?? null;
            if ($token) {
                SavedCard::updateOrCreate(
                    ['organization_id' => $org->id, 'provider' => 'moka', 'token' => $token],
                    [
                        'masked_pan' => $json['Data']['MaskedCardNumber'] ?? null,
                        'holder_name' => $data['card_holder_full_name'] ?? null,
                        'exp_month' => $data['exp_month'] ?? null,
                        'exp_year' => $data['exp_year'] ?? null,
                        'customer_code' => $data['customer_code'] ?? null,
                    ]
                );
            }
            $invoice->status = 'paid';
            $invoice->save();
        } else {
            $payment->status = 'failed';
            $payment->error = $resultMessage ?? ($json['Exception'] ?? 'Payment failed');
        }
        $payment->save();

        return response()->json(['payment' => $payment]);
    }

    public function threeDReturn(Request $request)
    {
        // Moka yönlendirmesinden dönen parametreleri al
        // Biz RedirectUrl'e inv paramını ekledik
        $invId = (int) $request->query('inv');
        $pid = (int) $request->query('pid');
        $result = $request->query('result') ?? $request->query('Result') ?? null; // 'T' or 'F'
        $orderId = $request->query('VirtualPosOrderId') ?? $request->query('OrderId') ?? null;
        $codeForHash = $request->query('CodeForHash') ?? $request->query('codeforhash') ?? null;

        if ($invId) {
            $invoice = SubscriptionInvoice::find($invId);
            if (!$invoice) {
                return redirect('/app/subscription?status=error');
            }
            // Organization context olmadan son payment kaydını bul
            $payment = Payment::where('subscription_invoice_id', $invoice->id)
                ->orderByDesc('id')
                ->first();

        // ReturnHash doğrulaması (opsiyonel: Moka dokümantasyonundaki şemaya göre uyarlanır)
        if ($codeForHash) {
            $secret = config('payments.moka.return_hash_secret');
            // Dokümana göre CodeForHash + 'T'/'F' eklenir ve hashlenir; burada sadece presence kontrolü örneklenmiştir.
            // İleride Moka'nın önerdiği hash formülü ile doğrulama yapılabilir.
        }

            if ($result === 'T') {
                if ($payment) {
                    $payment->status = 'captured';
                    if ($orderId) $payment->provider_txn_id = $orderId;
                    $payment->save();
                }
                $invoice->status = 'paid';
                $invoice->save();
                return redirect('/app/subscription?status=success');
            }

            if ($payment) {
                $payment->status = 'failed';
                $payment->save();
            }
            return redirect('/app/subscription?status=failed');
        }

        if ($pid) {
            $payment = Payment::find($pid);
            if (!$payment) { return redirect('/app/balance?status=error'); }
            if ($result === 'T') {
                $payment->status = 'captured';
                if ($orderId) $payment->provider_txn_id = $orderId;
                $payment->save();
                $packageCode = $payment->request_json['package'] ?? null;
                if ($packageCode) { $this->transferCreditsForPackage($payment->organization_id, $packageCode); }
                return redirect('/app/balance?status=success');
            }
            $payment->status = 'failed';
            $payment->save();
            return redirect('/app/balance?status=failed');
        }

        return redirect('/app?status=error');
    }

    public function payCredits(Request $request)
    {
        $org = $request->attributes->get('organization');
        $data = $request->validate([
            'package_code' => ['required','string'],
            'card_holder_full_name' => ['required','string','max:128'],
            'card_number' => ['required','string','max:22'],
            'exp_month' => ['required','string','max:2'],
            'exp_year' => ['required','string','max:4'],
            'cvc' => ['required','string','max:4'],
            'use_3d' => ['sometimes','boolean'],
            'redirect_url' => ['required_if:use_3d,true','url'],
        ]);

        $pkg = collect(config('billing.credit_packages'))->firstWhere('code', $data['package_code']);
        if (!$pkg) return response()->json(['message' => 'Geçersiz paket'], 422);

        // Havuz kapasitesi: rezervasyon öncesi kontrol (mevcut balance - açık rezervasyonlar)
        $ownerOrgId = (int) config('billing.owner_organization_id');
        $ownerWallet = \App\Models\CreditWallet::firstOrCreate(['organization_id' => $ownerOrgId]);
        $reserved = (int) CreditReservation::where('status','reserved')->sum('credits');
        $available = (float) ($ownerWallet->balance ?? 0) - (float) $reserved;
        if ($available < (float) $pkg['credits']) {
            return response()->json(['message' => 'insufficient_pool_credits'], 409);
        }

        $payment = Payment::create([
            'organization_id' => $org->id,
            'subscription_invoice_id' => null,
            'provider' => 'moka',
            'amount_try' => (float) $pkg['price_try'],
            'currency' => 'TRY',
            'status' => 'initiated',
            'request_json' => ['package' => $pkg['code']],
        ]);

        $reservation = CreditReservation::create([
            'buyer_organization_id' => $org->id,
            'payment_id' => $payment->id,
            'credits' => (int) $pkg['credits'],
            'status' => 'reserved',
            'expires_at' => now()->addMinutes(20),
        ]);

        $moka = MokaClient::fromConfig();
        $payload = [
            'CardHolderFullName' => $data['card_holder_full_name'],
            'CardNumber' => $data['card_number'],
            'ExpMonth' => $data['exp_month'],
            'ExpYear' => $data['exp_year'],
            'CvcNumber' => $data['cvc'],
            'Amount' => (float) number_format((float) $pkg['price_try'], 2, '.', ''),
            'Currency' => 'TL',
            'InstallmentNumber' => 1,
            'ClientIP' => $request->ip(),
            'OtherTrxCode' => 'PKG-'.$pkg['code'].'-'.$payment->id,
            'IsPoolPayment' => 0,
            'IsTokenized' => 0,
            'Software' => 'efatura.ai',
            'Description' => 'Kontör paketi',
            'IsPreAuth' => 0,
        ];

        if (!empty($data['use_3d'])) {
            $payload['ReturnHash'] = 1;
            $payload['RedirectType'] = 0;
            $payload['RedirectUrl'] = rtrim($data['redirect_url'], '?&').'?pid='.$payment->id;
            $resp = $moka->chargeThreeD($payload);
            $payment->request_json = array_merge($payment->request_json ?? [], ['3d' => true]);
            $payment->response_json = $resp['json'] ?? [];
            $payment->save();
            return response()->json(['three_d' => true, 'response' => $resp['json'] ?? []]);
        }

        $resp = $moka->charge($payload);
        $json = $resp['json'] ?? [];
        $payment->request_json = $payload;
        $payment->response_json = $json;
        if (($resp['status'] ?? false) && (($json['ResultCode'] ?? null) === 'Success')) {
            $payment->status = 'captured';
            $payment->provider_txn_id = $json['Data']['VirtualPosOrderId'] ?? null;
            $payment->save();
            $this->transferCreditsForPackage($org->id, $pkg['code']);
            CreditReservation::where('id', $reservation->id)->update(['status' => 'consumed']);
            return response()->json(['ok' => true, 'payment' => $payment]);
        }

        $payment->status = 'failed';
        $payment->error = $json['ResultMessage'] ?? 'Payment failed';
        $payment->save();
        CreditReservation::where('id', $reservation->id)->update(['status' => 'cancelled']);
        return response()->json(['message' => $payment->error], 422);
    }

    private function transferCreditsForPackage(int $orgId, string $packageCode): void
    {
        $pkg = collect(config('billing.credit_packages'))->firstWhere('code', $packageCode);
        if (!$pkg) return;
        $ownerOrgId = (int) config('billing.owner_organization_id');
        $ownerWallet = \App\Models\CreditWallet::firstOrCreate(['organization_id' => $ownerOrgId]);
        $userWallet = \App\Models\CreditWallet::firstOrCreate(['organization_id' => $orgId]);
        if (($ownerWallet->balance ?? 0) < (float) $pkg['credits']) return;
        \DB::transaction(function () use ($ownerWallet, $userWallet, $pkg) {
            $ownerWallet->balance = (float) $ownerWallet->balance - (float) $pkg['credits'];
            $ownerWallet->save();
            \App\Models\CreditTransaction::create([
                'credit_wallet_id' => $ownerWallet->id,
                'type' => 'debit',
                'amount' => (float) $pkg['credits'],
                'currency' => 'CREDITS',
                'metadata' => ['reason' => 'transfer_to_customer', 'package' => $pkg['code']],
            ]);
            $userWallet->balance = (float) $userWallet->balance + (float) $pkg['credits'];
            $userWallet->save();
            \App\Models\CreditTransaction::create([
                'credit_wallet_id' => $userWallet->id,
                'type' => 'credit',
                'amount' => (float) $pkg['credits'],
                'currency' => 'CREDITS',
                'metadata' => ['reason' => 'package_topup', 'package' => $pkg['code']],
            ]);
        });
    }
}


