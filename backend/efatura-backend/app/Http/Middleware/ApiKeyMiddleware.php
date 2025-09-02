<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ApiKey;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Api-Key');

        if (!$apiKey) {
            return response()->json([
                'code' => 'unauthorized',
                'message' => 'Missing X-Api-Key header',
            ], 401);
        }

        $key = ApiKey::where('key', $apiKey)
            ->whereNull('revoked_at')
            ->first();

        if (!$key) {
            return response()->json([
                'code' => 'forbidden',
                'message' => 'Invalid or revoked API key',
            ], 403);
        }

        // Basit izlenebilirlik için request kapsamına organization enjekte edelim
        $request->attributes->set('organization', $key->organization);

        // Son kullanım ve rate limit kontrolü
        $key->forceFill(['last_used_at' => now()])->saveQuietly();
        // IP allowlist kontrolü (env: API_KEY_IP_ALLOWLIST=ip1,ip2)
        $allowlist = array_filter(array_map('trim', explode(',', (string) env('API_KEY_IP_ALLOWLIST', ''))));
        if (!empty($allowlist)) {
            $ip = $request->ip();
            if (!in_array($ip, $allowlist, true)) {
                return response()->json([
                    'code' => 'forbidden_ip',
                    'message' => 'IP not allowed',
                ], 403);
            }
        }
        $maxPerMinute = (int) config('app.rate_per_minute', 120);
        if ($maxPerMinute > 0) {
            $bucketKey = 'rate:apikey:'.$key->id.':'.now()->format('YmdHi');
            $count = cache()->increment($bucketKey, 1);
            if ($count === 1) { cache()->put($bucketKey, 1, now()->addMinute()); }
            if ($count > $maxPerMinute) {
                return response()->json([
                    'code' => 'rate_limited',
                    'message' => 'Rate limit exceeded',
                ], 429);
            }
        }

        return $next($request);
    }
}
