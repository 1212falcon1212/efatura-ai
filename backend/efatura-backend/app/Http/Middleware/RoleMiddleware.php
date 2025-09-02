<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $org = $request->attributes->get('organization');
        if (!$org) {
            return response()->json(['message' => 'Organization not resolved'], 403);
        }
        $authHeader = $request->header('Authorization');
        if (! $authHeader || ! str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $raw = substr($authHeader, 7);
        $hash = hash('sha256', $raw);
        $userId = \DB::table('user_tokens')->where('token', $hash)->value('user_id');
        if (! $userId) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $currentRole = \DB::table('organization_user')
            ->where('organization_id', $org->id)
            ->where('user_id', $userId)
            ->value('role');

        $hierarchy = ['developer' => 10, 'owner' => 9, 'admin' => 8, 'finance' => 7, 'customer' => 1];
        $need = $hierarchy[strtolower($role)] ?? 0;
        $have = $hierarchy[strtolower((string) $currentRole)] ?? 0;
        if ($have < $need) {
            return response()->json(['message' => 'Forbidden: insufficient role'], 403);
        }
        return $next($request);
    }
}
