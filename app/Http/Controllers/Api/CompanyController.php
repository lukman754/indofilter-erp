<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Company::with(['phones', 'bankAccounts'])->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'alias' => 'nullable|string|max:255',
            'logo' => 'nullable|string',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'npwp' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'phones' => 'nullable|array',
            'phones.*.phone' => 'required|string|max:50',
            'phones.*.label' => 'nullable|string|max:100',
            'bank_accounts' => 'nullable|array',
            'bank_accounts.*.bank_name' => 'required|string|max:255',
            'bank_accounts.*.account_name' => 'required|string|max:255',
            'bank_accounts.*.account_number' => 'required|string|max:100',
            'bank_accounts.*.is_default' => 'sometimes|boolean',
        ]);

        $company = Company::create($validated);

        if ($request->has('phones')) {
            $company->phones()->createMany($request->phones);
        }

        if ($request->has('bank_accounts')) {
            $bankAccounts = $request->bank_accounts;
            $defaultFound = false;
            foreach ($bankAccounts as $key => $acc) {
                if (!empty($acc['is_default'])) {
                    if ($defaultFound) {
                        $bankAccounts[$key]['is_default'] = false;
                    } else {
                        $defaultFound = true;
                    }
                } else {
                    $bankAccounts[$key]['is_default'] = false;
                }
            }
            $company->bankAccounts()->createMany($bankAccounts);
        }

        return response()->json($company->load(['phones', 'bankAccounts']), 201);
    }

    public function show(Company $company): JsonResponse
    {
        return response()->json($company->load(['phones', 'bankAccounts']));
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'alias' => 'nullable|string|max:255',
            'logo' => 'nullable|string',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'npwp' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'phones' => 'nullable|array',
            'phones.*.id' => 'nullable|integer|exists:company_phones,id',
            'phones.*.phone' => 'required|string|max:50',
            'phones.*.label' => 'nullable|string|max:100',
            'bank_accounts' => 'nullable|array',
            'bank_accounts.*.id' => 'nullable|integer|exists:company_bank_accounts,id',
            'bank_accounts.*.bank_name' => 'required|string|max:255',
            'bank_accounts.*.account_name' => 'required|string|max:255',
            'bank_accounts.*.account_number' => 'required|string|max:100',
            'bank_accounts.*.is_default' => 'sometimes|boolean',
        ]);

        $company->update($validated);

        if ($request->has('phones')) {
            $this->syncRelation($company, 'phones', $request->phones);
        }

        if ($request->has('bank_accounts')) {
            $bankAccounts = $request->bank_accounts;
            $defaultFound = false;
            foreach ($bankAccounts as $key => $acc) {
                if (!empty($acc['is_default'])) {
                    if ($defaultFound) {
                        $bankAccounts[$key]['is_default'] = false;
                    } else {
                        $defaultFound = true;
                    }
                } else {
                    $bankAccounts[$key]['is_default'] = false;
                }
            }
            $hasDefault = collect($bankAccounts)->contains('is_default', true);
            if ($hasDefault) {
                $company->bankAccounts()->update(['is_default' => false]);
            }
            $this->syncRelation($company, 'bankAccounts', $bankAccounts);
        }

        return response()->json($company->load(['phones', 'bankAccounts']));
    }

    public function destroy(Company $company): JsonResponse
    {
        $company->delete();

        return response()->json(['message' => 'Company deleted successfully.']);
    }

    private function syncRelation(Company $company, string $relation, array $items): void
    {
        $existingIds = $company->{$relation}->pluck('id')->toArray();
        $incomingIds = [];

        foreach ($items as $item) {
            if (isset($item['id'])) {
                $incomingIds[] = $item['id'];
                $company->{$relation}()->where('id', $item['id'])->update($item);
            } else {
                $created = $company->{$relation}()->create($item);
                $incomingIds[] = $created->id;
            }
        }

        $toDelete = array_diff($existingIds, $incomingIds);
        if (!empty($toDelete)) {
            $company->{$relation}()->whereIn('id', $toDelete)->delete();
        }
    }
}
