<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WebhookSubscription;
use App\Models\WebhookDelivery;

class WebhookController extends Controller
{
    public function subscriptions(Request $request)
    {
        $org = $request->attributes->get('organization');
        return response()->json(['data' => WebhookSubscription::where('organization_id', $org->id)->orderByDesc('id')->get()]);
    }

    public function createSubscription(Request $request)
    {
        $org = $request->attributes->get('organization');
        $data = $request->validate([
            'url' => ['required','url'],
            'secret' => ['required','string'],
            'events' => ['required','array','min:1'],
        ]);
        $w = WebhookSubscription::create([
            'organization_id' => $org->id,
            'url' => $data['url'],
            'secret' => $data['secret'],
            'events' => $data['events'],
        ]);
        return response()->json($w, 201);
    }

    public function deleteSubscription(Request $request, int $id)
    {
        $org = $request->attributes->get('organization');
        $w = WebhookSubscription::where('organization_id', $org->id)->findOrFail($id);
        $w->delete();
        return response()->noContent();
    }

    public function deliveries(Request $request)
    {
        $org = $request->attributes->get('organization');
        $query = WebhookDelivery::where('organization_id', $org->id);
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        // Tekli ya da çoklu event filtrelemesi
        if ($event = $request->query('event')) {
            $query->where('event', $event);
        } elseif ($events = $request->query('events')) {
            // events[]=invoice.sent&events[]=invoice.failed şeklinde
            $arr = is_array($events) ? $events : explode(',', (string) $events);
            $arr = array_filter(array_map('trim', $arr));
            if (!empty($arr)) { $query->whereIn('event', $arr); }
        }
        // Tarih aralığı (created_at)
        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', date('Y-m-d H:i:s', strtotime($from)));
        }
        if ($to = $request->query('to')) {
            $query->where('created_at', '<=', date('Y-m-d H:i:s', strtotime($to)));
        }
        if ($subId = $request->query('subscription_id')) {
            $query->where('webhook_subscription_id', (int) $subId);
        }
        $perPage = min(max((int) $request->query('per_page', 50), 1), 200);
        $items = $query->orderByDesc('id')->paginate($perPage);
        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
            ],
        ]);
    }

    public function replay(Request $request, int $id)
    {
        $org = $request->attributes->get('organization');
        $delivery = WebhookDelivery::where('organization_id', $org->id)->findOrFail($id);
        $delivery->status = 'pending';
        $delivery->attempt_count = 0;
        $delivery->save();
        \App\Jobs\SendWebhookDelivery::dispatch($delivery->id)->onQueue('default');
        return response()->json($delivery, 202);
    }
}


