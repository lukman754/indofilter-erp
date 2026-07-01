<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Partner;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function globalSearch(Request $request): JsonResponse
    {
        $q = $request->query('q');
        if (empty($q) || strlen(trim($q)) < 2) {
            return response()->json([
                'documents' => [],
                'partners' => [],
                'products' => [],
            ]);
        }

        $term = '%' . $q . '%';

        // 1. Search Documents (across all companies!)
        $documents = Document::with(['company', 'partner'])
            ->where(function ($query) use ($term) {
                $query->where('document_number', 'like', $term)
                    ->orWhere('type', 'like', $term)
                    ->orWhere('status', 'like', $term)
                    ->orWhereHas('partner', function ($sub) use ($term) {
                        $sub->where('name', 'like', $term);
                    });
            })
            ->limit(10)
            ->get();

        // 2. Search Partners (across all companies!)
        $partners = Partner::with('company')
            ->where('name', 'like', $term)
            ->orWhere('phone', 'like', $term)
            ->orWhere('email', 'like', $term)
            ->orWhere('contact_person', 'like', $term)
            ->limit(10)
            ->get();

        // 3. Search Products (across all companies!)
        $products = Product::with('company')
            ->where('name', 'like', $term)
            ->orWhere('code', 'like', $term)
            ->limit(10)
            ->get();

        return response()->json([
            'documents' => $documents,
            'partners' => $partners,
            'products' => $products,
        ]);
    }
}
