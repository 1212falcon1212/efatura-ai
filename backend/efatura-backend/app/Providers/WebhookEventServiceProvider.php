<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Webhooks\WebhookService;
use App\Models\Invoice;

class WebhookEventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Invoice::created(function (Invoice $invoice) {
            app(WebhookService::class)->queueEvent($invoice->organization_id, 'invoice.created', [
                'id' => $invoice->id,
                'status' => $invoice->status,
            ]);
        });
        Invoice::updated(function (Invoice $invoice) {
            if ($invoice->wasChanged('status')) {
                $event = match ($invoice->status) {
                    'sent' => 'invoice.sent',
                    'failed' => 'invoice.failed',
                    'canceled' => 'invoice.canceled',
                    default => null,
                };
                if ($event) {
                    app(WebhookService::class)->queueEvent($invoice->organization_id, $event, [
                        'id' => $invoice->id,
                        'status' => $invoice->status,
                    ]);
                }
            }
        });
    }
}


