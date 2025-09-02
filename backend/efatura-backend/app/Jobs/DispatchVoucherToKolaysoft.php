<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\Kolaysoft\KolaysoftClient;
use App\Services\Credits\CreditService;

class DispatchVoucherToKolaysoft implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $backoff = 30;

    public function __construct(
        public string $type, // 'SMM'|'MM'
        public array $voucherXMLList,
        public int $organizationId,
        public ?int $invoiceId = null
    ) {
    }

    public function handle(): void
    {
        $client = new KolaysoftClient();
        $result = $this->type === 'MM'
            ? $client->sendMM($this->voucherXMLList)
            : $client->sendSMM($this->voucherXMLList);

        if (($result['success'] ?? false) === true) {
            $creditsPerDoc = (int) (config('billing.doc_cost_credits.voucher') ?? 1);
            $creditService = app(CreditService::class);
            if ($creditService->hasSufficientCustomerCredits($this->organizationId, $creditsPerDoc)) {
                $invoice = $this->invoiceId ? \App\Models\Invoice::find($this->invoiceId) : null;
                if ($invoice) {
                    $creditService->debitCustomerCreditsForInvoice($invoice, $creditsPerDoc);
                    $creditService->debitPoolCreditsForInvoice($invoice);
                } else {
                    // invoice referansı yoksa sadece havuzdan düşün
                    $fake = new \App\Models\Invoice(['id' => 0, 'organization_id' => $this->organizationId]);
                    $creditService->debitPoolCreditsForInvoice($fake);
                }
            } else {
                // limit yetersiz: engelle
                \App\Models\DeadLetter::create([
                    'type' => 'voucher.send',
                    'reference_id' => $this->invoiceId ?? 0,
                    'queue' => $this->queue ?? 'default',
                    'error' => 'insufficient_customer_credits',
                    'payload' => ['organization_id' => $this->organizationId, 'voucher_type' => $this->type],
                ]);
                return;
            }
            return;
        }

        $this->release($this->backoff);
    }
}

