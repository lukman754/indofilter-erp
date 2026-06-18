<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $companyId = $request->query('company_id');

        $types = ['quotation', 'proforma_invoice', 'invoice', 'delivery_slip', 'delivery_address', 'purchase_order'];
        $stats = [];

        $query = Document::whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $totalCount = (clone $query)->count();
        $stats['total_all'] = $totalCount;

        foreach ($types as $type) {
            $typeQuery = clone $query;
            $typeQuery->where('type', $type);

            $count = $typeQuery->count();
            $stats[$type] = $count;
        }

        return response()->json($stats);
    }
}
