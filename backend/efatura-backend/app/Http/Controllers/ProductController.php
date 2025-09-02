<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $org = $request->attributes->get('organization');
        $query = Product::where('organization_id', $org->id);
        // Basit arama
        if ($q = $request->input('q')) {
            $query->where(function($w) use ($q) {
                $w->where('name', 'like', "%$q%")
                  ->orWhere('sku', 'like', "%$q%")
                ;
            });
        }
        // Sıralama
        $sort = $request->input('sort');
        $order = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        switch ($sort) {
            case 'name':
                $query->orderBy('name', $order)->orderBy('id', 'desc');
                break;
            case 'sku':
                $query->orderBy('sku', $order)->orderBy('id', 'desc');
                break;
            case 'id':
                $query->orderBy('id', $order);
                break;
            default:
                $query->orderBy('id', 'desc');
        }
        $limit = min(200, max(1, (int) $request->input('page.limit', 50)));
        $after = $request->input('page.after');
        if ($after) {
            $query->where('id', '<', (int) $after);
        }
        $items = $query->limit($limit + 1)->get();
        $next = null;
        if ($items->count() > $limit) {
            $next = (string) $items->last()->id;
            $items = $items->slice(0, $limit)->values();
        }
        $resp = response()->json(['data' => $items]);
        if ($next) { $resp->headers->set('X-Next-Cursor', $next); }
        return $resp;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $org = $request->attributes->get('organization');
        $data = $request->validate([
            'name' => ['required','string'],
            'sku' => ['required','string'],
            'unit' => ['nullable','string'],
            'vat_rate' => ['nullable','numeric'],
            'unit_price' => ['nullable','numeric'],
            'currency' => ['nullable','string','size:3'],
            'metadata' => ['nullable','array'],
        ]);
        $exists = Product::where('organization_id',$org->id)->where('sku',$data['sku'])->exists();
        if ($exists) {
            return response()->json(['code'=>'conflict','message'=>'SKU already exists'],409);
        }
        $product = Product::create(array_merge([
            'organization_id' => $org->id,
        ], $data));
        return response()->json($product, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $org = $request->attributes->get('organization');
        $item = Product::where('organization_id', $org->id)->findOrFail($id);
        return response()->json($item);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $org = $request->attributes->get('organization');
        $data = $request->validate([
            'name' => ['sometimes','required','string'],
            'sku' => ['sometimes','required','string'],
            'unit' => ['nullable','string'],
            'vat_rate' => ['nullable','numeric'],
            'unit_price' => ['nullable','numeric'],
            'currency' => ['nullable','string','size:3'],
            'metadata' => ['nullable','array'],
        ]);

        $product = Product::where('organization_id', $org->id)->findOrFail($id);
        if (array_key_exists('sku', $data)) {
            $exists = Product::where('organization_id',$org->id)
                ->where('sku',$data['sku'])
                ->where('id','<>',$product->id)
                ->exists();
            if ($exists) {
                return response()->json(['code'=>'conflict','message'=>'SKU already exists'],409);
            }
        }
        $product->fill($data);
        $product->save();
        return response()->json($product);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $org = request()->attributes->get('organization');
        $product = Product::where('organization_id', $org->id)->findOrFail($id);
        $product->delete();
        return response()->json(['deleted' => true]);
    }
}
