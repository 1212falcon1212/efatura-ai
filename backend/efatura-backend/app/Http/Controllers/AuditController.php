<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AuditLog;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $org = $request->attributes->get('organization');
        $q = AuditLog::where('organization_id', $org->id)->orderByDesc('id');
        $limit = min(200, max(1, (int) $request->input('page.limit', 50)));
        $after = $request->input('page.after');
        if ($after) { $q->where('id', '<', (int) $after); }
        if ($a = $request->input('action')) { $q->where('action', $a); }
        $items = $q->limit($limit + 1)->get();
        $next = null; if ($items->count() > $limit) { $next = (string) $items->last()->id; $items = $items->slice(0, $limit)->values(); }
        $resp = response()->json(['data' => $items]); if ($next) { $resp->headers->set('X-Next-Cursor', $next); }
        return $resp;
    }
}


