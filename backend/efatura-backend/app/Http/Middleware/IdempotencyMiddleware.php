<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\{IdempotencyKey, Organization};
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Idempotency-Key');
        if (!$key) {
            return response()->json([
                'code' => 'invalid_request',
                'message' => 'Missing X-Idempotency-Key header',
            ], 400);
        }

        /** @var \App\Models\Organization $org */
        $org = $request->attributes->get('organization');
        if (!$org) {
            return response()->json(['code' => 'unauthorized', 'message' => 'Organization not resolved'], 401);
        }

        $method = $request->getMethod();
        $path = $request->getPathInfo();
        $body = $request->getContent();
        $reqHash = hash('sha256', $method.'|'.$path.'|'.$body);

        $existing = IdempotencyKey::where('organization_id', $org->id)
            ->where('idempotency_key', $key)
            ->first();

        if ($existing) {
            // Eğer mevcutsa, kayıtlı yanıtı döndür (varsa)
            if ($existing->response_code !== null) {
                return response($existing->response_body, $existing->response_code, [
                    'Content-Type' => 'application/json',
                    'X-Idempotency-Replayed' => 'true',
                ]);
            }
            // Aksi halde geç; eşzamanlı durumda kilit gerekir (ileri geliştirme)
        } else {
            IdempotencyKey::create([
                'organization_id' => $org->id,
                'idempotency_key' => $key,
                'method' => $method,
                'path' => $path,
                'request_hash' => $reqHash,
                'expires_at' => now()->addDay(),
            ]);
        }

        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        if ($response->headers->get('Content-Type') === null) {
            $response->headers->set('Content-Type', 'application/json');
        }

        IdempotencyKey::where('organization_id', $org->id)
            ->where('idempotency_key', $key)
            ->update([
                'response_code' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
            ]);

        return $response;
    }
}
