<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Str;
use App\Models\AuditLog;

class SubscriptionController extends Controller
{
    public function plans(Request $request)
    {
        $plans = Plan::where('active', true)->orderBy('price_try')->get();
        return response()->json(['data' => $plans]);
    }

    public function current(Request $request)
    {
        $org = $request->attributes->get('organization');
        $sub = Subscription::with(['plan', 'invoices' => function ($q) {
            $q->orderByDesc('id');
        }])->where('organization_id', $org->id)->orderByDesc('id')->first();
        return response()->json($sub);
    }

    public function subscribe(Request $request)
    {
        $org = $request->attributes->get('organization');
        $data = $request->validate(['plan_code' => ['required','string']]);
        $plan = Plan::where('code', $data['plan_code'])->where('active', true)->firstOrFail();

        $today = today();
        $sub = Subscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'current_period_start' => $today,
            'current_period_end' => $today->copy()->addMonth(),
        ]);

        // İlk abonelik faturası (ödeme sistemini kurmadan, kayıt amaçlı)
        \App\Models\SubscriptionInvoice::create([
            'subscription_id' => $sub->id,
            'number' => 'SUB'.date('Ymd').Str::padLeft((string) $sub->id, 6, '0'),
            'amount_try' => (int) $plan->price_try,
            'issue_date' => $today,
            'status' => 'pending',
        ]);
        AuditLog::create([
            'organization_id' => $org->id,
            'user_id' => null,
            'action' => 'subscription.create',
            'entity_type' => 'subscription',
            'entity_id' => $sub->id,
            'context' => ['plan' => $plan->code],
        ]);
        return response()->json($sub, 201);
    }

    public function cancel(Request $request)
    {
        $org = $request->attributes->get('organization');
        $sub = Subscription::where('organization_id', $org->id)->orderByDesc('id')->firstOrFail();
        $sub->status = 'canceled';
        $sub->save();
        AuditLog::create([
            'organization_id' => $org->id,
            'user_id' => null,
            'action' => 'subscription.cancel',
            'entity_type' => 'subscription',
            'entity_id' => $sub->id,
            'context' => [],
        ]);
        return response()->json(['ok' => true]);
    }
}


