<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DocsController extends Controller
{
    public function openapi(): BinaryFileResponse
    {
        // Proje kökünde api/openapi.yaml mevcut
        $path = base_path('api/openapi.yaml');
        return response()->file($path, [
            'Content-Type' => 'application/yaml; charset=utf-8',
            'Cache-Control' => 'no-cache',
        ]);
    }

    public function healthcheckPing(Request $request)
    {
        $url = config('services.healthchecks.url');
        if (!$url) {
            return response()->json(['ok' => false, 'reason' => 'HEALTHCHECKS_URL yok'], 400);
        }
        try {
            $res = Http::timeout(5)->get($url);
            return response()->json(['ok' => $res->successful(), 'status' => $res->status()]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function redoc(): \Illuminate\Http\Response
    {
        $html = <<<'HTML'
<!doctype html>
<html lang="tr">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>API Dokümanı • efatura.ai</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" />
    <style>body,html{margin:0;height:100%} #redoc{height:100vh}</style>
  </head>
  <body>
    <redoc id="redoc" spec-url="/api/v1/docs/openapi.yaml"></redoc>
    <script src="https://cdn.jsdelivr.net/npm/redoc@next/bundles/redoc.standalone.js"></script>
  </body>
</html>
HTML;
        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-cache']);
    }
}


