<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Despatch;
use App\Jobs\DispatchDespatchToKolaysoft;

class DespatchController extends Controller
{
    public function index(Request $request)
    {
        $org = $request->attributes->get('organization');
        $query = Despatch::where('organization_id', $org->id)->orderByDesc('id');
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($mode = $request->input('mode')) {
            $query->where('mode', $mode);
        }
        if ($ettn = $request->input('ettn')) {
            $query->where('ettn', 'like', "%$ettn%");
        }

        $limit = min(200, max(1, (int) $request->input('page.limit', 50)));
        $after = $request->input('page.after');
        if ($after) {
            $query->where('id', '<', (int) $after);
        }

        $items = $query->limit($limit + 1)->get();
        $nextCursor = null;
        if ($items->count() > $limit) {
            $nextCursor = (string) $items->last()->id;
            $items = $items->slice(0, $limit)->values();
        }

        $response = response()->json(['data' => $items]);
        if ($nextCursor) {
            $response->headers->set('X-Next-Cursor', $nextCursor);
        }
        return $response;
    }

    public function show(Request $request, int $id)
    {
        $org = $request->attributes->get('organization');
        $d = Despatch::where('organization_id', $org->id)->findOrFail($id);
        return response()->json($d);
    }

    public function store(Request $request)
    {
        $org = $request->attributes->get('organization');
        $data = $request->validate([
            'mode' => ['required','in:despatch,receipt'],
            'xml' => ['nullable','string'],
            'ettn' => ['nullable','string'],
        ]);
        $despatch = Despatch::create([
            'organization_id' => $org->id,
            'mode' => $data['mode'],
            'xml' => $data['xml'] ?? null,
            'ettn' => $data['ettn'] ?? null,
            'status' => 'queued',
        ]);

        $list = [[ 'documentUUID' => $despatch->ettn, 'xml' => $despatch->xml ]];
        DispatchDespatchToKolaysoft::dispatch($list, $org->id, $despatch->mode);
        return response()->json($despatch, 201);
    }

    public function cancel(Request $request, int $id)
    {
        $org = $request->attributes->get('organization');
        $d = Despatch::where('organization_id', $org->id)->findOrFail($id);
        if (! in_array($d->status, ['queued','processing'])) {
            return response()->json(['code' => 'conflict', 'message' => 'Despatch not cancelable'], 409);
        }
        $d->update(['status' => 'canceled']);
        return response()->json($d);
    }

    public function retry(Request $request, int $id)
    {
        $org = $request->attributes->get('organization');
        $d = Despatch::where('organization_id', $org->id)->findOrFail($id);
        if ($d->status !== 'failed') {
            return response()->json(['code' => 'conflict', 'message' => 'Retry allowed only for failed despatches'], 409);
        }
        $d->update(['status' => 'queued']);
        return response()->json($d, 202);
    }
}

