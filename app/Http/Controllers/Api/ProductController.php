<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()->with(['company']);

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'code' => 'nullable|string|max:100',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'uom' => 'nullable|string|max:50',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $product = Product::create($validated);

        return response()->json($product, 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json($product);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'sometimes|exists:companies,id',
            'code' => 'nullable|string|max:100',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'uom' => 'nullable|string|max:50',
            'price' => 'sometimes|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $product->update($validated);

        return response()->json($product);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully.']);
    }
}
