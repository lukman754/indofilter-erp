<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashTransactionController extends Controller
{
    /**
     * List transactions with optional filters.
     * Includes a running balance column computed in PHP.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CashTransaction::query()->with('creator');

        if ($request->filled('account_id')) {
            $query->where('cash_account_id', $request->account_id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        $startingBalances = [];
        if ($request->filled('date_from')) {
            $startQuery = CashTransaction::query()
                ->select('cash_account_id', DB::raw("SUM(CASE WHEN type='in' THEN amount ELSE -amount END) as balance"))
                ->whereDate('date', '<', $request->date_from)
                ->groupBy('cash_account_id');

            if ($request->filled('account_id')) {
                $startQuery->where('cash_account_id', $request->account_id);
            }

            $startingBalances = $startQuery->pluck('balance', 'cash_account_id')->toArray();

            $query->whereDate('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        $transactions = $query->orderBy('date')->orderBy('id')->get();

        // Compute running balance per account
        $runningBalances = [];
        $result = $transactions->map(function ($t) use (&$runningBalances, $startingBalances) {
            $key = $t->cash_account_id;
            if (!isset($runningBalances[$key])) {
                $runningBalances[$key] = (float) ($startingBalances[$key] ?? 0);
            }
            $delta = $t->type === 'in' ? $t->amount : -$t->amount;
            $runningBalances[$key] += $delta;

             return [
                'id'              => $t->id,
                'cash_account_id' => $t->cash_account_id,
                'type'            => $t->type,
                'amount'          => $t->amount,
                'formula'         => $t->formula,
                'description'     => $t->description,
                'pic'             => $t->pic,
                'is_marked'       => $t->is_marked,
                'date'            => $t->date->format('Y-m-d'),
                'running_balance' => $runningBalances[$key],
                'created_by'      => $t->creator?->name,
                'created_at'      => $t->created_at,
            ];
        });

        $totalStarting = 0;
        if ($request->filled('date_from')) {
            $totalStarting = (float) array_sum($startingBalances);
        } else {
            // If no date_from, starting balance is 0 (beginning of time)
            $totalStarting = 0.0;
        }

        $finalBalance = count($result) > 0 ? (float) $result[count($result) - 1]['running_balance'] : $totalStarting;

        return response()->json([
            'transactions'     => $result,
            'starting_balance' => $totalStarting,
            'ending_balance'   => $finalBalance,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cash_account_id' => 'required|exists:cash_accounts,id',
            'type'            => 'required|in:in,out',
            'amount'          => 'required|numeric|min:0.01',
            'formula'         => 'nullable|string|max:500',
            'description'     => 'nullable|string',
            'pic'             => 'nullable|string|max:100',
            'is_marked'       => 'nullable|boolean',
            'date'            => 'required|date',
        ]);

        $validated['created_by'] = $request->user()?->id;

        $transaction = CashTransaction::create($validated);
        $transaction->load('account');

        return response()->json([
            'id'              => $transaction->id,
            'cash_account_id' => $transaction->cash_account_id,
            'type'            => $transaction->type,
            'amount'          => $transaction->amount,
            'formula'         => $transaction->formula,
            'description'     => $transaction->description,
            'pic'             => $transaction->pic,
            'is_marked'       => $transaction->is_marked,
            'date'            => $transaction->date->format('Y-m-d'),
        ], 201);
    }

    public function update(Request $request, CashTransaction $cashTransaction): JsonResponse
    {
        $validated = $request->validate([
            'cash_account_id' => 'sometimes|exists:cash_accounts,id',
            'type'            => 'sometimes|in:in,out',
            'amount'          => 'sometimes|numeric|min:0.01',
            'formula'         => 'nullable|string|max:500',
            'description'     => 'nullable|string',
            'pic'             => 'nullable|string|max:100',
            'is_marked'       => 'sometimes|boolean',
            'date'            => 'sometimes|date',
        ]);

        $cashTransaction->update($validated);

        return response()->json([
            'id'              => $cashTransaction->id,
            'cash_account_id' => $cashTransaction->cash_account_id,
            'type'            => $cashTransaction->type,
            'amount'          => $cashTransaction->amount,
            'formula'         => $cashTransaction->formula,
            'description'     => $cashTransaction->description,
            'pic'             => $cashTransaction->pic,
            'is_marked'       => $cashTransaction->is_marked,
            'date'            => $cashTransaction->date->format('Y-m-d'),
        ]);

    }

    public function destroy(CashTransaction $cashTransaction): JsonResponse
    {
        $cashTransaction->delete();
        return response()->json(['message' => 'Transaksi berhasil dihapus.']);
    }

    /**
     * Monthly/yearly summary grouped by cash account.
     * Params: year (required), month (optional)
     */
    public function summary(Request $request): JsonResponse
    {
        $year  = $request->integer('year', now()->year);
        $month = $request->filled('month') ? $request->integer('month') : null;

        $query = CashTransaction::query()
            ->whereYear('date', $year);

        if ($month) {
            $query->whereMonth('date', $month);
        }

        $rows = $query->select(
                'cash_account_id',
                DB::raw("SUM(CASE WHEN type='in'  THEN amount ELSE 0 END) as total_in"),
                DB::raw("SUM(CASE WHEN type='out' THEN amount ELSE 0 END) as total_out"),
                DB::raw('COUNT(*) as transaction_count')
            )
            ->groupBy('cash_account_id')
            ->with('account:id,name')
            ->get();

        $result = $rows->map(fn($r) => [
            'cash_account_id'   => $r->cash_account_id,
            'account_name'      => $r->account?->name ?? '-',
            'total_in'          => (float) $r->total_in,
            'total_out'         => (float) $r->total_out,
            'balance'           => (float) ($r->total_in - $r->total_out),
            'transaction_count' => $r->transaction_count,
        ]);

        // Overall totals
        $overallIn  = $result->sum('total_in');
        $overallOut = $result->sum('total_out');

        return response()->json([
            'year'        => $year,
            'month'       => $month,
            'accounts'    => $result->values(),
            'overall_in'  => $overallIn,
            'overall_out' => $overallOut,
            'overall_balance' => $overallIn - $overallOut,
        ]);
    }

    public function bulkAction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:cash_transactions,id',
            'action' => 'required|in:mark,unmark,delete',
        ]);

        $ids = $validated['ids'];
        $action = $validated['action'];

        if ($action === 'delete') {
            CashTransaction::whereIn('id', $ids)->delete();
            return response()->json(['message' => 'Transaksi berhasil dihapus.']);
        }

        if ($action === 'mark') {
            CashTransaction::whereIn('id', $ids)->update(['is_marked' => true]);
            return response()->json(['message' => 'Transaksi berhasil ditandai.']);
        }

        if ($action === 'unmark') {
            CashTransaction::whereIn('id', $ids)->update(['is_marked' => false]);
            return response()->json(['message' => 'Tanda transaksi berhasil dihapus.']);
        }

        return response()->json(['message' => 'Aksi tidak valid.'], 400);
    }
}
