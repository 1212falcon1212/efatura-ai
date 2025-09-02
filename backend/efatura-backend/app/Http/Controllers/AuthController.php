<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Organization;
use Illuminate\Validation\Rules\Password;
use App\Models\ApiKey;
use App\Models\AuditLog;

class AuthController extends Controller
{
    public function signup(Request $request)
    {
        $data = $request->validate([
            'email' => ['required','email','unique:users,email'],
            'password' => ['required', Password::min(8)],
            'organization' => ['required','string'],
            // Fatura için organizasyon profil alanları (TCKN hariç zorunlu)
            'vkn' => ['required_without:tckn','digits:10'],
            'tckn' => ['nullable','digits:11'],
            'tax_office' => ['required','string','max:255'],
            'address' => ['required','string','max:1000'],
            'city' => ['required','string','max:255'],
            'district' => ['required','string','max:255'],
            'phone' => ['required','string','max:50'],
            'company_title' => ['required','string','max:255'],
            'postal_code' => ['required','string','max:20'],
            'country' => ['required','string','max:100'],
            'company_email' => ['required','email'],
        ]);

        $user = User::create([
            'name' => $data['email'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $orgSettings = [
            'company_title' => $data['company_title'] ?? $data['organization'],
            'vkn' => $data['vkn'] ?? null,
            'tckn' => $data['tckn'] ?? null,
            'tax_office' => $data['tax_office'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'district' => $data['district'] ?? null,
            'phone' => $data['phone'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'country' => $data['country'] ?? 'Türkiye',
            'company_email' => $data['company_email'] ?? null,
            // URN alanları signup'ta alınmıyor; API ayarları sayfasından güncellenecek
            'e_invoice_gb_urn' => null,
            'e_invoice_pk_urn' => null,
        ];
        $org = Organization::create([
            'name' => $data['organization'],
            'settings' => $orgSettings,
        ]);
        // Varsayılan rol: 'customer'. İlk kullanıcı veya yetkili e-posta(lar) owner olabilir.
        $role = 'customer';
        $ownerEmails = array_filter(array_map('trim', explode(',', (string) env('OWNER_EMAILS'))));
        if (User::count() === 1 /* az önce oluşturulan bu ilk kullanıcı */ || in_array($user->email, $ownerEmails, true)) {
            $role = 'owner';
        }
        $org->users()->attach($user->id, ['role' => $role]);

        // API Key üret
        $prefix = app()->isProduction() ? 'live_' : 'test_';
        $apiKeyPlain = $prefix.Str::random(40);
        ApiKey::create([
            'organization_id' => $org->id,
            'name' => 'Default',
            'key' => $apiKeyPlain,
        ]);
        AuditLog::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'action' => 'apikey.create',
            'entity_type' => 'apikey',
            'entity_id' => 0,
            'context' => ['name' => 'Default'],
        ]);

        $token = base64_encode(Str::random(40));
        \DB::table('user_tokens')->insert([
            'user_id' => $user->id,
            'name' => 'default',
            'token' => hash('sha256', $token),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['token' => $token, 'apiKey' => $apiKeyPlain, 'role' => $role], 201);
    }

    public function orgSettings(Request $request)
    {
        $org = $request->attributes->get('organization');
        if (!$org) return response()->json(['message' => 'Unauthorized'], 401);
        return response()->json(['data' => [
            'name' => $org->name,
            'settings' => $org->settings ?? new \stdClass(),
        ]]);
    }

    public function updateOrgSettings(Request $request)
    {
        $org = $request->attributes->get('organization');
        if (!$org) return response()->json(['message' => 'Unauthorized'], 401);

        $data = $request->validate([
            'name' => ['sometimes','string','max:255'],
            'settings' => ['sometimes','array'],
            'settings.company_title' => ['nullable','string','max:255'],
            'settings.vkn' => ['nullable','digits:10'],
            'settings.tckn' => ['nullable','digits:11'],
            'settings.tax_office' => ['nullable','string','max:255'],
            'settings.address' => ['nullable','string','max:1000'],
            'settings.city' => ['nullable','string','max:255'],
            'settings.district' => ['nullable','string','max:255'],
            'settings.phone' => ['nullable','string','max:50'],
            'settings.postal_code' => ['nullable','string','max:20'],
            'settings.country' => ['nullable','string','max:100'],
            'settings.company_email' => ['nullable','email'],
            // URN alanları API ayarlarında yönetilecek
            'settings.e_invoice_gb_urn' => ['nullable','string','max:255'],
            'settings.e_invoice_pk_urn' => ['nullable','string','max:255'],
        ]);

        if (array_key_exists('name', $data)) {
            $org->name = (string) $data['name'];
        }
        if (array_key_exists('settings', $data)) {
            $merged = array_merge($org->settings ?? [], $data['settings']);
            $org->settings = $merged;
        }
        $org->save();
        AuditLog::create([
            'organization_id' => $org->id,
            'user_id' => optional($this->currentUserFromToken($request))->id,
            'action' => 'organization.update_settings',
            'entity_type' => 'organization',
            'entity_id' => $org->id,
            'context' => ['keys' => array_keys($data)],
        ]);
        return response()->json(['ok' => true]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required','email'],
            'password' => ['required'],
        ]);
        // Basit brute-force throttling (email bazlı 5 deneme/5dk)
        $key = 'login:'.sha1(strtolower($data['email']));
        $attempts = cache()->increment($key);
        if ($attempts === 1) { cache()->put($key, 1, now()->addMinutes(5)); }
        if ($attempts > 5) {
            return response()->json(['code' => 'too_many_attempts', 'message' => 'Çok fazla deneme. Lütfen 5 dakika sonra tekrar deneyin.'], 429);
        }
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['code' => 'invalid_credentials', 'message' => 'Geçersiz giriş bilgileri'], 401);
        }
        // Başarılı girişte throttling sayaçlarını sıfırla
        cache()->forget($key);
        $token = base64_encode(Str::random(40));
        \DB::table('user_tokens')->insert([
            'user_id' => $user->id,
            'name' => 'login',
            'token' => hash('sha256', $token),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Kullanıcının ait olduğu ilk organizasyonun API anahtarını döndür (yoksa oluştur)
        $orgId = \DB::table('organization_user')
            ->where('user_id', $user->id)
            ->orderByDesc('role')
            ->value('organization_id');
        $apiKeyPlain = null;
        if ($orgId) {
            $apiKeyPlain = ApiKey::where('organization_id', $orgId)->value('key');
            if (!$apiKeyPlain) {
                $prefix = app()->isProduction() ? 'live_' : 'test_';
                $apiKeyPlain = $prefix.Str::random(40);
                ApiKey::create([
                    'organization_id' => $orgId,
                    'name' => 'Default',
                    'key' => $apiKeyPlain,
                ]);
            }
        }
        return response()->json(['token' => $token, 'apiKey' => $apiKeyPlain]);
    }

    public function me(Request $request)
    {
        $user = $this->currentUserFromToken($request);
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'createdAt' => $user->created_at,
        ]);
    }

    public function logout(Request $request)
    {
        $raw = self::extractBearerToken($request);
        if (!$raw) return response()->json(['message' => 'Unauthorized'], 401);
        \DB::table('user_tokens')->where('token', hash('sha256', $raw))->delete();
        return response()->json(['ok' => true]);
    }

    public function organizations(Request $request)
    {
        $user = $this->currentUserFromToken($request);
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);
        $orgIds = \DB::table('organization_user')
            ->where('user_id', $user->id)
            ->pluck('organization_id')
            ->all();
        $items = [];
        foreach ($orgIds as $oid) {
            $org = \App\Models\Organization::find($oid);
            if (!$org) continue;
            $apiKeyPlain = \App\Models\ApiKey::where('organization_id', $org->id)->value('key');
            if (!$apiKeyPlain) {
                $prefix = app()->isProduction() ? 'live_' : 'test_';
                $apiKeyPlain = $prefix.\Illuminate\Support\Str::random(40);
                \App\Models\ApiKey::create([
                    'organization_id' => $org->id,
                    'name' => 'Default',
                    'key' => $apiKeyPlain,
                ]);
            }
            $items[] = [
                'id' => $org->id,
                'name' => $org->name,
                'apiKey' => $apiKeyPlain,
            ];
        }
        return response()->json(['data' => $items]);
    }

    public function changePassword(Request $request)
    {
        $user = $this->currentUserFromToken($request);
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);
        $data = $request->validate([
            'oldPassword' => ['required','string'],
            'newPassword' => ['required', Password::min(8)],
        ]);
        if (! Hash::check($data['oldPassword'], $user->password)) {
            return response()->json(['message' => 'Mevcut şifre yanlış'], 422);
        }
        $user->password = Hash::make($data['newPassword']);
        $user->save();
        return response()->json(['ok' => true]);
    }

    public function updateProfile(Request $request)
    {
        $user = $this->currentUserFromToken($request);
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);
        $data = $request->validate([
            'name' => ['required','string','max:255'],
        ]);
        $user->name = $data['name'];
        $user->save();
        return response()->json(['ok' => true]);
    }

    public function apiKeys(Request $request)
    {
        $org = $request->attributes->get('organization');
        $keys = \App\Models\ApiKey::where('organization_id', $org->id)->orderByDesc('id')->get();
        return response()->json(['data' => $keys]);
    }

    public function createApiKey(Request $request)
    {
        $org = $request->attributes->get('organization');
        $data = $request->validate([
            'name' => ['required','string','max:255'],
        ]);
        $prefix = app()->isProduction() ? 'live_' : 'test_';
        $plain = $prefix.\Illuminate\Support\Str::random(40);
        $key = \App\Models\ApiKey::create([
            'organization_id' => $org->id,
            'name' => $data['name'],
            'key' => $plain,
        ]);
        AuditLog::create([
            'organization_id' => $org->id,
            'user_id' => optional($this->currentUserFromToken($request))->id,
            'action' => 'apikey.create',
            'entity_type' => 'apikey',
            'entity_id' => $key->id,
            'context' => ['name' => $data['name']],
        ]);
        return response()->json(['id' => $key->id, 'key' => $plain], 201);
    }

    public function revokeApiKey(Request $request, int $id)
    {
        $org = $request->attributes->get('organization');
        $key = \App\Models\ApiKey::where('organization_id', $org->id)->findOrFail($id);
        $key->revoked_at = now();
        $key->save();
        AuditLog::create([
            'organization_id' => $org->id,
            'user_id' => optional($this->currentUserFromToken($request))->id,
            'action' => 'apikey.revoke',
            'entity_type' => 'apikey',
            'entity_id' => $key->id,
            'context' => [],
        ]);
        return response()->json(['ok' => true]);
    }

    private static function extractBearerToken(Request $request): ?string
    {
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }
        return null;
    }

    private function currentUserFromToken(Request $request): ?User
    {
        $raw = self::extractBearerToken($request);
        if (! $raw) return null;
        $hash = hash('sha256', $raw);
        $userId = \DB::table('user_tokens')->where('token', $hash)->value('user_id');
        if (! $userId) return null;
        return User::find($userId);
    }

    // Owner: başka bir organizasyondaki bir kullanıcı adına token üret (impersonate)
    public function impersonate(Request $request)
    {
        $data = $request->validate([
            'organization_id' => ['required','integer'],
        ]);
        $orgId = (int) $data['organization_id'];
        $org = Organization::findOrFail($orgId);
        // Hedef organizasyondaki en yüksek rollü kullanıcıyı bul
        $pivot = \DB::table('organization_user')
            ->where('organization_id', $orgId)
            ->orderByDesc('role')
            ->first();
        if (!$pivot) return response()->json(['message' => 'Organization has no users'], 422);
        $user = User::find($pivot->user_id);
        if (!$user) return response()->json(['message' => 'User not found'], 422);

        $token = base64_encode(Str::random(40));
        \DB::table('user_tokens')->insert([
            'user_id' => $user->id,
            'name' => 'impersonate',
            'token' => hash('sha256', $token),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $apiKeyPlain = \App\Models\ApiKey::where('organization_id', $orgId)->value('key');
        if (!$apiKeyPlain) {
            $prefix = app()->isProduction() ? 'live_' : 'test_';
            $apiKeyPlain = $prefix.Str::random(40);
            \App\Models\ApiKey::create([
                'organization_id' => $orgId,
                'name' => 'Default',
                'key' => $apiKeyPlain,
            ]);
        }
        AuditLog::create([
            'organization_id' => $orgId,
            'user_id' => optional($this->currentUserFromToken($request))->id,
            'action' => 'user.impersonate',
            'entity_type' => 'organization',
            'entity_id' => $orgId,
            'context' => ['impersonated_user_id' => $user->id],
        ]);
        return response()->json(['token' => $token, 'apiKey' => $apiKeyPlain, 'organizationId' => $orgId, 'role' => $pivot->role]);
    }
}


