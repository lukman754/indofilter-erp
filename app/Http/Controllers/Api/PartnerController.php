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
        $query = Partner::with('company')->withCount('documents');

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('alias', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%");
            });
        }

        $sortableColumns = ['name', 'type', 'phone', 'email', 'contact_person', 'documents_count'];
        $sortBy = in_array($request->input('sort_by'), $sortableColumns, true)
            ? $request->input('sort_by')
            : 'name';
        $sortDirection = $request->input('sort_direction') === 'desc' ? 'desc' : 'asc';

        return response()->json($query
            ->orderBy($sortBy, $sortDirection)
            ->orderBy('id', 'asc')
            ->paginate(min((int) $request->input('per_page', 10), 100)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'type' => 'required|in:customer,vendor',
            'name' => 'required|string|max:255',
            'alias' => 'nullable|string|max:255|unique:partners,alias',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'npwp' => 'nullable|string|max:50',
            'contact_person' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:255',
        ], [
            'alias.unique' => 'Alias ini sudah digunakan oleh partner lain. Silakan gunakan alias yang berbeda.'
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
            'alias' => 'nullable|string|max:255|unique:partners,alias,' . $partner->id,
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'npwp' => 'nullable|string|max:50',
            'contact_person' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:255',
        ], [
            'alias.unique' => 'Alias ini sudah digunakan oleh partner lain. Silakan gunakan alias yang berbeda.'
        ]);

        $partner->update($validated);

        // Update all related documents' recipient details when partner changes
        $documents = \App\Models\Document::where('partner_id', $partner->id)->get();
        foreach ($documents as $document) {
            $docData = [
                'recipient_name' => $partner->name,
                'recipient_address' => $partner->address,
                'recipient_pic' => $partner->contact_person,
                'recipient_phone' => $partner->phone,
            ];

            if ($partner->type === 'vendor') {
                $docData['vendor_bank_name'] = $partner->bank_name;
                $docData['vendor_bank_account_name'] = $partner->bank_account_name;
                $docData['vendor_bank_account_number'] = $partner->bank_account_number;
            }

            $document->update($docData);

            if ($document->status === 'confirmed') {
                try {
                    app(\App\Http\Controllers\Api\DocumentController::class)->generateAndSaveLocalFile($document, true);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to regenerate file for document ID {$document->id} on partner update: " . $e->getMessage());
                }
            }
        }

        return response()->json($partner);
    }

    public function destroy(Partner $partner): JsonResponse
    {
        $partner->delete();

        return response()->json(['message' => 'Partner deleted successfully.']);
    }
}
