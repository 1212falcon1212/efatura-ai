<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AutoPurchaseCredits extends Command
{
    protected $signature = 'credits:auto-purchase';
    protected $description = 'Auto purchase credit packages for wallets under threshold';

    public function handle(): int
    {
        $wallets = \App\Models\CreditWallet::where('auto_purchase_enabled', true)
            ->whereNotNull('auto_purchase_package')
            ->whereNotNull('auto_purchase_threshold_credits')
            ->get();
        $count = 0;
        foreach ($wallets as $w) {
            $balance = (float) ($w->balance ?? 0);
            $th = (int) $w->auto_purchase_threshold_credits;
            if ($th > 0 && $balance <= $th) {
                try {
                    $org = \App\Models\Organization::find($w->organization_id);
                    if (!$org) continue;
                    // Varsayılan olarak 3D istemeyen akış, kart tokenizasyonu hazırsa geliştirilebilir
                    $request = new \Illuminate\Http\Request([
                        'package_code' => $w->auto_purchase_package,
                        'card_holder_full_name' => 'AUTO',
                        'card_number' => '4111111111111111', // PLACEHOLDER
                        'exp_month' => '12',
                        'exp_year' => '2030',
                        'cvc' => '000',
                        'use_3d' => false,
                    ]);
                    $request->setUserResolver(function () use ($org) { return null; });
                    app(\App\Http\Controllers\PaymentController::class)->payCredits($request);
                    $count++;
                } catch (\Throwable $e) {
                    \Log::error('auto_purchase_failed', ['wallet_id' => $w->id, 'error' => $e->getMessage()]);
                }
            }
        }
        $this->info('Auto purchase attempted for '.$count.' wallets');
        return 0;
    }
}


