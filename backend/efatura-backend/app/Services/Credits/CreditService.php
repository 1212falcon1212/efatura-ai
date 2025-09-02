<?php

namespace App\Services\Credits;

use App\Models\{CreditWallet, CreditTransaction, Invoice};
use Illuminate\Support\Facades\DB;

class CreditService
{
    public function debitForInvoice(Invoice $invoice, float $amount, string $currency = 'TRY'): void
    {
        DB::transaction(function () use ($invoice, $amount, $currency) {
            $wallet = CreditWallet::firstOrCreate(['organization_id' => $invoice->organization_id], [
                'currency' => $currency,
            ]);

            $wallet->refresh();
            $wallet->balance = ($wallet->balance ?? 0) - $amount;
            $wallet->save();

            // Low balance alert & optional auto-topup
            $threshold = (float) ($wallet->low_balance_threshold ?? 0);
            if ($threshold > 0 && (float) $wallet->balance <= $threshold) {
                // Webhook uyarısı
                try {
                    $subs = \App\Models\WebhookSubscription::where('organization_id', $invoice->organization_id)
                        ->whereJsonContains('events', 'wallet.low_balance')
                        ->get();
                    foreach ($subs as $sub) {
                        $delivery = \App\Models\WebhookDelivery::create([
                            'organization_id' => $invoice->organization_id,
                            'webhook_subscription_id' => $sub->id,
                            'event' => 'wallet.low_balance',
                            'payload' => [
                                'organization_id' => $invoice->organization_id,
                                'balance' => (float) $wallet->balance,
                                'threshold' => $threshold,
                            ],
                            'status' => 'pending',
                            'attempt_count' => 0,
                        ]);
                        \App\Jobs\SendWebhookDelivery::dispatch($delivery->id);
                    }
                } catch (\Throwable $e) {
                    // noop
                }

                if ($wallet->auto_topup_enabled && (float) ($wallet->auto_topup_amount ?? 0) > 0) {
                    $topup = (float) $wallet->auto_topup_amount;
                    $wallet->balance = (float) $wallet->balance + $topup;
                    $wallet->save();
                    CreditTransaction::create([
                        'credit_wallet_id' => $wallet->id,
                        'type' => 'credit',
                        'amount' => $topup,
                        'currency' => $currency,
                        'metadata' => [ 'reason' => 'auto_topup' ],
                    ]);
                }
            }

            CreditTransaction::create([
                'credit_wallet_id' => $wallet->id,
                'type' => 'debit',
                'amount' => $amount,
                'currency' => $currency,
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'reason' => 'invoice_sent',
                ],
            ]);
        });
    }

    /**
     * Havuzdan kontör düş: Doküman başına e‑Kontör tüketimi.
     */
    public function debitPoolCreditsForInvoice(Invoice $invoice): void
    {
        $ownerOrgId = (int) config('billing.owner_organization_id');
        $credits = (int) (config('billing.doc_cost_credits.invoice') ?? 1);
        DB::transaction(function () use ($ownerOrgId, $credits, $invoice) {
            $wallet = CreditWallet::firstOrCreate(['organization_id' => $ownerOrgId]);
            $wallet->refresh();
            $wallet->balance = (float) ($wallet->balance ?? 0) - (float) $credits;
            $wallet->save();
            CreditTransaction::create([
                'credit_wallet_id' => $wallet->id,
                'type' => 'debit',
                'amount' => (float) $credits,
                'currency' => 'CREDITS',
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'reason' => 'pool_invoice_sent',
                ],
            ]);
        });
    }

    /**
     * Müşterinin (organizasyon) yeterli e‑Kontöre sahip olup olmadığını kontrol eder.
     */
    public function hasSufficientCustomerCredits(int $organizationId, int $neededCredits): bool
    {
        $wallet = CreditWallet::firstOrCreate(['organization_id' => $organizationId]);
        return (float) ($wallet->balance ?? 0) >= (float) $neededCredits;
    }

    /**
     * Müşteri cüzdanından e‑Kontör düş.
     */
    public function debitCustomerCreditsForInvoice(Invoice $invoice, int $credits): void
    {
        DB::transaction(function () use ($invoice, $credits) {
            $wallet = CreditWallet::firstOrCreate(['organization_id' => $invoice->organization_id]);
            $wallet->refresh();
            $current = (float) ($wallet->balance ?? 0);
            if ($current < (float) $credits) {
                throw new \RuntimeException('insufficient_customer_credits');
            }
            $wallet->balance = $current - (float) $credits;
            $wallet->save();
            CreditTransaction::create([
                'credit_wallet_id' => $wallet->id,
                'type' => 'debit',
                'amount' => (float) $credits,
                'currency' => 'CREDITS',
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'reason' => 'customer_invoice_sent',
                ],
            ]);
        });
    }
}

