<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SendWebhookDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $deliveryId)
    {
    }

    public int $tries = 5;

    public function backoff(): array
    {
        return [30, 60, 120, 300, 600];
    }

    public function handle(): void
    {
        $delivery = WebhookDelivery::with('subscription')->find($this->deliveryId);
        if (!$delivery) { return; }
        if (!in_array($delivery->status, ['pending','retrying'], true)) { return; }

        $subscription = $delivery->subscription;
        if (!$subscription) {
            $delivery->status = 'failed';
            $delivery->response_status = 0;
            $delivery->response_body = 'Subscription not found';
            $delivery->save();
            return;
        }

        // Hazırlık
        $delivery->last_attempt_at = now();
        $delivery->attempt_count = (int) $delivery->attempt_count + 1;
        $delivery->save();

        $url = (string) $subscription->url;
        $payload = $delivery->payload ?? [];
        $event = (string) $delivery->event;
        $timestamp = (string) now()->timestamp;

        // İmzalama (opsiyonel)
        $signatureHeader = null;
        $secret = (string) ($subscription->secret ?? '');
        if ($secret !== '') {
            $baseString = $timestamp . '.' . json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $sig = hash_hmac('sha256', $baseString, $secret);
            $signatureHeader = 't='.$timestamp.',v1='.$sig;
        }

        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Webhook-Event' => $event,
            'X-Webhook-Timestamp' => $timestamp,
        ];
        if ($signatureHeader) { $headers['X-Webhook-Signature'] = $signatureHeader; }

        $response = Http::timeout(15)->withHeaders($headers)->post($url, $payload);

        $delivery->response_status = $response->status();
        $delivery->response_body = mb_substr((string) $response->body(), 0, 4000);

        if ($response->successful()) {
            $delivery->status = 'sent';
            $delivery->save();
            return;
        }

        if ($this->attempts() >= $this->tries) {
            $delivery->status = 'failed';
            $delivery->save();
            return;
        }

        $delivery->status = 'retrying';
        $delivery->save();
        throw new \RuntimeException('Webhook delivery failed with status '.$response->status());
    }
}


