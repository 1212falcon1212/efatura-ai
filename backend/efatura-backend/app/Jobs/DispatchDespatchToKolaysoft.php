<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\Kolaysoft\KolaysoftClient;

class DispatchDespatchToKolaysoft implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $backoff = 30;

    public function __construct(
        public array $inputDocumentList,
        public int $organizationId,
        public string $mode = 'despatch' // 'despatch' | 'receipt'
    ) {
    }

    public function handle(): void
    {
        $client = new KolaysoftClient();
        $result = $this->mode === 'receipt'
            ? $client->sendReceiptAdvice($this->inputDocumentList)
            : $client->sendDespatch($this->inputDocumentList);
        if (($result['success'] ?? false) === true) {
            $creditsPerDoc = (int) (config('billing.doc_cost_credits.despatch') ?? 1);
            $creditService = app(\App\Services\Credits\CreditService::class);
            if ($creditService->hasSufficientCustomerCredits($this->organizationId, $creditsPerDoc)) {
                // Despatch tarafında invoice kaydı yok: sahte bir invoice objesi ile düşüm yapalım
                $fake = new \App\Models\Invoice(['id' => 0, 'organization_id' => $this->organizationId]);
                $creditService->debitCustomerCreditsForInvoice($fake, $creditsPerDoc);
                $creditService->debitPoolCreditsForInvoice($fake);
            } else {
                // limit yetersiz: engelle
                \App\Models\DeadLetter::create([
                    'type' => 'despatch.send',
                    'reference_id' => 0,
                    'queue' => $this->queue ?? 'default',
                    'error' => 'insufficient_customer_credits',
                    'payload' => ['organization_id' => $this->organizationId, 'mode' => $this->mode],
                ]);
                return;
            }
            return;
        }
        $this->release($this->backoff);
    }
}

