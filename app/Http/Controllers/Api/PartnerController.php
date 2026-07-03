<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Partner::with('company');

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'type' => 'required|in:customer,vendor',
            'name' => 'required|string|max:255',
            'alias' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'npwp' => 'nullable|string|max:50',
            'contact_person' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:255',
        ]);

        $partner = Partner::create($validated);

        return response()->json($partner, 201);
    }

    public function show(Partner $partner): JsonResponse
    {
        return response()->json($partner->load('documents'));
    }

    public function update(Request $request, Partner $partner): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'sometimes|exists:companies,id',
            'type' => 'sometimes|in:customer,vendor',
            'name' => 'sometimes|string|max:255',
            'alias' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'npwp' => 'nullable|string|max:50',
            'contact_person' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:255',
        ]);

        $partner->update($validated);

        return response()->json($partner);
    }

    public function destroy(Partner $partner): JsonResponse
    {
        $partner->delete();

        return response()->json(['message' => 'Partner deleted successfully.']);
    }
}
