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

        $baseQuery = Document::query();
        if ($companyId) {
            $baseQuery->where('company_id', $companyId);
        }

        // --- Monthly stats (current month) ---
        $monthQuery = (clone $baseQuery)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month);

        $stats['total_all'] = (clone $monthQuery)->count();
        foreach ($types as $type) {
            $stats[$type] = (clone $monthQuery)->where('type', $type)->count();
        }

        // --- Status breakdown (current month) ---
        $stats['draft'] = (clone $monthQuery)->where('status', 'draft')->count();
        $stats['confirmed'] = (clone $monthQuery)->where('status', 'confirmed')->count();
        $stats['canceled'] = (clone $monthQuery)->where('status', 'canceled')->count();

        // --- Financial (current month) ---
        $confirmedMonth = (clone $monthQuery)->where('status', 'confirmed');
        $stats['month_revenue'] = (clone $confirmedMonth)->where('type', 'invoice')->sum('grand_total');
        $stats['month_quotation_value'] = (clone $monthQuery)->where('type', 'quotation')->sum('grand_total');
        $stats['month_invoice_value'] = (clone $monthQuery)->where('type', 'invoice')->sum('grand_total');
        $stats['month_dp_total'] = (clone $monthQuery)->where('payment_type', 'dp')->where('status', 'confirmed')->sum('dp_amount');

        // --- All-time totals ---
        $allQuery = clone $baseQuery;
        $stats['all_time_count'] = (clone $allQuery)->count();
        $stats['all_time_revenue'] = (clone $allQuery)->where('type', 'invoice')->where('status', 'confirmed')->sum('grand_total');
        $stats['all_time_quotation'] = (clone $allQuery)->where('type', 'quotation')->count();
        $stats['all_time_invoice'] = (clone $allQuery)->where('type', 'invoice')->count();

        // --- Recent documents (last 10) ---
        $stats['recent_documents'] = (clone $baseQuery)
            ->with('partner:id,name')
            ->latest()
            ->limit(10)
            ->get(['id', 'type', 'document_number', 'date', 'status', 'grand_total', 'partner_id']);

        // --- Pending actions (draft documents) ---
        $stats['pending_documents'] = (clone $baseQuery)
            ->with('partner:id,name')
            ->where('status', 'draft')
            ->latest()
            ->limit(5)
            ->get(['id', 'type', 'document_number', 'date', 'status', 'grand_total', 'partner_id']);

        // --- Monthly trend (last 6 months) ---
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $trendQuery = (clone $baseQuery)
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month);

            $trend[] = [
                'month' => $month->format('M Y'),
                'count' => (clone $trendQuery)->count(),
                'revenue' => (clone $trendQuery)->where('type', 'invoice')->where('status', 'confirmed')->sum('grand_total'),
            ];
        }
        $stats['monthly_trend'] = $trend;

        return response()->json($stats);
    }
}
