<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MockController extends Controller
{
    public function handle(Request $request, string $any = '')
    {
        // Map request to fixture file: METHOD_path with slashes replaced by underscores
        $method = strtoupper($request->method());
        $pathKey = trim($any, '/');
        if ($pathKey === '') { $pathKey = 'index'; }
        $filename = $method . '_' . str_replace('/', '_', $pathKey) . '.json';
        $full = storage_path('app/mock/' . $filename);
        if (!is_file($full)) {
            return response()->json([
                'ok' => false,
                'error' => 'Mock fixture bulunamadı',
                'file' => $filename,
            ], 404);
        }
        $raw = file_get_contents($full);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return response()->json(['ok' => false, 'error' => 'Mock dosyası geçersiz JSON'], 500);
        }
        $headers = [];
        if (isset($data['_headers']) && is_array($data['_headers'])) {
            $headers = $data['_headers'];
            unset($data['_headers']);
        }
        return response()->json($data, 200, $headers);
    }
}


