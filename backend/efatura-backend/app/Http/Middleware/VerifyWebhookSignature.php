<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next)
    {
        $signature = (string) $request->header('X-Webhook-Signature', '');
        $timestamp = (string) $request->header('X-Webhook-Timestamp', '');
        $secret = (string) config('webhook.secret', env('WEBHOOK_INCOMING_SECRET', ''));

        if ($secret === '' || $signature === '' || $timestamp === '') {
            throw new AccessDeniedHttpException('Missing webhook signature');
        }

        // Signature format: t=TIMESTAMP,v1=HMAC
        $parts = [];
        foreach (explode(',', $signature) as $seg) {
            $kv = explode('=', trim($seg), 2);
            if (count($kv) === 2) { $parts[$kv[0]] = $kv[1]; }
        }
        $provided = $parts['v1'] ?? '';
        if ($provided === '') { throw new AccessDeniedHttpException('Invalid webhook signature'); }

        // Freshness check (5 dakika)
        if (abs(((int)$timestamp) - time()) > 300) {
            throw new AccessDeniedHttpException('Stale webhook timestamp');
        }

        $payload = $request->getContent();
        $baseString = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $baseString, $secret);

        if (!hash_equals($expected, $provided)) {
            throw new AccessDeniedHttpException('Webhook signature mismatch');
        }

        return $next($request);
    }
}


