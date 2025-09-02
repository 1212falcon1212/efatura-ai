<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Invitation;
use App\Models\User;

class InvitationController extends Controller
{
    public function create(Request $request)
    {
        $org = $request->attributes->get('organization');
        $data = $request->validate([
            'email' => ['required','email'],
            'role' => ['required','in:owner,admin,finance,developer,customer'],
        ]);
        $token = Str::random(40);
        $inv = Invitation::create([
            'organization_id' => $org->id,
            'email' => $data['email'],
            'role' => $data['role'],
            'token' => $token,
            'expires_at' => now()->addDays(7),
        ]);
        return response()->json(['invite' => $inv, 'url' => url('/api/v1/invitations/accept?token='.$token)], 201);
    }

    public function list(Request $request)
    {
        $org = $request->attributes->get('organization');
        $items = Invitation::where('organization_id', $org->id)->orderByDesc('id')->get();
        return response()->json(['data' => $items]);
    }

    // Public accept endpoint (no API key)
    public function accept(Request $request)
    {
        $data = $request->validate(['token' => ['required','string']]);
        $inv = Invitation::where('token', $data['token'])->firstOrFail();
        if ($inv->accepted_at) {
            return response()->json(['message' => 'Already accepted'], 409);
        }
        if ($inv->expires_at && $inv->expires_at->isPast()) {
            return response()->json(['message' => 'Invitation expired'], 410);
        }
        // Eğer kullanıcı varsa bağla, yoksa sadece accepted işaretle (uygulama akışına göre signup tetiklenebilir)
        $user = User::where('email', $inv->email)->first();
        if ($user) {
            \DB::table('organization_user')->updateOrInsert(
                ['organization_id' => $inv->organization_id, 'user_id' => $user->id],
                ['role' => $inv->role]
            );
        }
        $inv->accepted_at = now();
        $inv->save();
        return response()->json(['ok' => true]);
    }
}


