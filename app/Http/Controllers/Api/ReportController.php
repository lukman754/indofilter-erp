<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Document::with([
            'partner',
            'items',
            'bankAccount.company',
        ]);

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->filled('type')) {
            $type = strtolower(str_replace(' ', '_', $request->type));
            $query->where('type', $type);
        }

        if ($request->filled('month')) {
            $date = \Carbon\Carbon::parse($request->month);
            $query->whereYear('date', $date->year)
                  ->whereMonth('date', $date->month);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('document_number', 'like', "%{$search}%")
                  ->orWhere('customer_po_number', 'like', "%{$search}%")
                  ->orWhereHas('partner', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%")
                        ->orWhere('alias', 'like', "%{$search}%");
                  });
            });
        }

        $documents = $query->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 25));

        return response()->json($documents);
    }

    public function stats(Request $request): JsonResponse
    {
        $query = Document::query();

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->filled('type')) {
            $type = strtolower(str_replace(' ', '_', $request->type));
            $query->where('type', $type);
        }

        if ($request->filled('month')) {
            $date = \Carbon\Carbon::parse($request->month);
            $query->whereYear('date', $date->year)
                  ->whereMonth('date', $date->month);
        }

        $stats = (clone $query)->selectRaw('
            COUNT(*) as total_documents,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as draft_count,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as confirmed_count,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as canceled_count,
            SUM(subtotal) as total_subtotal,
            SUM(discount) as total_discount,
            SUM(tax) as total_tax,
            SUM(dp_amount) as total_dp,
            SUM(grand_total) as total_grand_total,
            AVG(grand_total) as avg_grand_total
        ', ['draft', 'confirmed', 'canceled'])->first();

        $typeBreakdown = (clone $query)
            ->selectRaw('type, COUNT(*) as count, SUM(grand_total) as total')
            ->groupBy('type')
            ->get()
            ->map(function ($row) {
                return [
                    'type' => $row->type,
                    'count' => (int) $row->count,
                    'total' => (float) $row->total,
                ];
            });

        return response()->json([
            'stats' => $stats,
            'type_breakdown' => $typeBreakdown,
        ]);
    }
}
