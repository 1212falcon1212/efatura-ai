<?php

namespace App\Services\Webhooks;

use App\Models\WebhookSubscription;
use App\Models\WebhookDelivery;
use App\Jobs\DeliverWebhook;

class WebhookService
{
    /**
     * Queue webhook deliveries for all subscriptions listening the given event.
     */
    public function queueEvent(int $organizationId, string $event, array $payload): void
    {
        WebhookSubscription::where('organization_id', $organizationId)
            ->whereJsonContains('events', $event)
            ->chunkById(100, function ($subs) use ($organizationId, $event, $payload) {
                foreach ($subs as $sub) {
                    $delivery = WebhookDelivery::create([
                        'organization_id' => $organizationId,
                        'webhook_subscription_id' => $sub->id,
                        'event' => $event,
                        'payload' => $payload,
                        'status' => 'pending',
                        'attempt_count' => 0,
                    ]);
                    DeliverWebhook::dispatch($delivery->id);
                }
            });
    }
}


