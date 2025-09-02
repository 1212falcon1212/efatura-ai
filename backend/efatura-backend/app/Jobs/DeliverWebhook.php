<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 8;
    public int $backoff = 60; // seconds

    public function __construct(public int $deliveryId)
    {
    }

    public function handle(): void
    {
        $delivery = WebhookDelivery::with('subscription')->find($this->deliveryId);
        if (! $delivery) {
            return;
        }
        if ($delivery->status === 'delivered') {
            return;
        }

        $subscription = $delivery->subscription;
        $payload = is_array($delivery->payload) ? $delivery->payload : json_decode($delivery->payload, true);
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->getTimestamp();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $subscription->secret);

        $response = Http::withHeaders([
            'X-EF-Event' => $delivery->event,
            'X-EF-Timestamp' => $timestamp,
            'X-EF-Signature' => 'sha256='.$signature,
            'Content-Type' => 'application/json',
        ])->timeout(10)->post($subscription->url, $payload);

        $delivery->attempt_count = ($delivery->attempt_count ?? 0) + 1;
        $delivery->last_attempt_at = now();
        $delivery->response_status = $response->status();
        $delivery->response_body = (string) $response->body();

        if ($response->successful()) {
            $delivery->status = 'delivered';
            $delivery->save();
            return;
        }

        $delivery->status = 'failed';
        $delivery->save();
        // exponential backoff strategy by releases
        $this->release(min(3600, $this->backoff * max(1, $delivery->attempt_count)));
    }
}


