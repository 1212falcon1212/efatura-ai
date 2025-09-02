<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Voucher;
use App\Jobs\DispatchVoucherToKolaysoft;

class VoucherController extends Controller
{
    public function index(Request $request)
    {
        $org = $request->attributes->get('organization');
        $query = Voucher::where('organization_id', $org->id)->orderByDesc('id');
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($type = $request->input('type')) {
            $query->where('type', $type);
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
        $voucher = Voucher::where('organization_id', $org->id)->findOrFail($id);
        return response()->json($voucher);
    }

    public function store(Request $request)
    {
        $org = $request->attributes->get('organization');
        $data = $request->validate([
            'type' => ['required','in:SMM,MM'],
            'xml' => ['nullable','string'],
            'ettn' => ['nullable','string'],
            'destinationEmail' => ['nullable','string'],
        ]);
        $voucher = Voucher::create([
            'organization_id' => $org->id,
            'type' => $data['type'],
            'xml' => $data['xml'] ?? null,
            'ettn' => $data['ettn'] ?? null,
            'destination_email' => $data['destinationEmail'] ?? null,
            'status' => 'queued',
        ]);

        $list = [[
            'documentUUID' => $voucher->ettn,
            'xml' => $voucher->xml,
            'destinationEmail' => $voucher->destination_email,
        ]];
        DispatchVoucherToKolaysoft::dispatch($voucher->type, $list, $org->id, null);
        return response()->json($voucher, 201);
    }

    public function cancel(Request $request, int $id)
    {
        $org = $request->attributes->get('organization');
        $voucher = Voucher::where('organization_id', $org->id)->findOrFail($id);
        if (! in_array($voucher->status, ['queued','processing'])) {
            return response()->json(['code' => 'conflict', 'message' => 'Voucher not cancelable'], 409);
        }
        $voucher->update(['status' => 'canceled']);
        return response()->json($voucher);
    }

    public function retry(Request $request, int $id)
    {
        $org = $request->attributes->get('organization');
        $voucher = Voucher::where('organization_id', $org->id)->findOrFail($id);
        if ($voucher->status !== 'failed') {
            return response()->json(['code' => 'conflict', 'message' => 'Retry allowed only for failed vouchers'], 409);
        }
        $voucher->update(['status' => 'queued']);
        return response()->json($voucher, 202);
    }
}

