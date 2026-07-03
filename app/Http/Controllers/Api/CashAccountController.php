<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashAccountController extends Controller
{
    public function index(): JsonResponse
    {
        $accounts = CashAccount::all()->map(function ($account) {
            return [
                'id'          => $account->id,
                'name'        => $account->name,
                'description' => $account->description,
                'balance'     => $account->balance,
                'created_at'  => $account->created_at,
            ];
        });

        return response()->json($accounts);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        $account = CashAccount::create($validated);

        return response()->json([
            'id'          => $account->id,
            'name'        => $account->name,
            'description' => $account->description,
            'balance'     => 0,
        ], 201);
    }

    public function update(Request $request, CashAccount $cashAccount): JsonResponse
    {
        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:100',
            'description' => 'nullable|string',
        ]);

        $cashAccount->update($validated);

        return response()->json([
            'id'          => $cashAccount->id,
            'name'        => $cashAccount->name,
            'description' => $cashAccount->description,
            'balance'     => $cashAccount->balance,
        ]);
    }

    public function destroy(CashAccount $cashAccount): JsonResponse
    {
        $cashAccount->delete();
        return response()->json(['message' => 'Akun kas berhasil dihapus.']);
    }
}
