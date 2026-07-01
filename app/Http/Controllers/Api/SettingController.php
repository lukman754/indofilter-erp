<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function getSettings(): JsonResponse
    {
        $companies = Company::where('is_active', true)->get(['id', 'name', 'alias']);

        $documentTypes = [
            'quotation',
            'proforma_invoice',
            'invoice',
            'delivery_slip',
            'delivery_address',
            'purchase_order',
        ];

        $globalFolders = [];
        foreach ($documentTypes as $type) {
            $globalFolders[$type] = Setting::get("local_path_{$type}", '');
        }

        $companyFolders = [];
        foreach ($companies as $company) {
            $companyFolders[$company->id] = [];
            foreach ($documentTypes as $type) {
                $companyFolders[$company->id][$type] = Setting::get("local_path_{$type}", '', $company->id);
            }
        }

        return response()->json([
            'global_folders' => $globalFolders,
            'company_folders' => $companyFolders,
            'companies' => $companies,
            'documents_storage_path' => Setting::get('documents_storage_path', ''),
            'gemini_api_key' => Setting::get('gemini_api_key', ''),
            'gemini_model' => Setting::get('gemini_model', 'gemini-1.5-flash'),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'global_folders' => 'nullable|array',
            'company_folders' => 'nullable|array',
            'documents_storage_path' => 'nullable|string',
            'gemini_api_key' => 'nullable|string',
            'gemini_model' => 'nullable|string',
        ]);

        Setting::set('documents_storage_path', $request->documents_storage_path ?: '');
        if ($request->has('gemini_api_key')) {
            Setting::set('gemini_api_key', $request->gemini_api_key ?: '');
        }
        if ($request->has('gemini_model')) {
            Setting::set('gemini_model', $request->gemini_model ?: 'gemini-1.5-flash');
        }

        $documentTypes = [
            'quotation',
            'proforma_invoice',
            'invoice',
            'delivery_slip',
            'delivery_address',
            'purchase_order',
        ];

        if ($request->has('global_folders')) {
            foreach ($documentTypes as $type) {
                $val = $request->input("global_folders.{$type}", '');
                Setting::set("local_path_{$type}", $val ?: '');
            }
        }

        if ($request->has('company_folders')) {
            foreach ($request->input('company_folders') as $companyId => $folders) {
                foreach ($documentTypes as $type) {
                    $val = $folders[$type] ?? '';
                    Setting::set("local_path_{$type}", $val ?: '', (int)$companyId);
                }
            }
        }

        return response()->json(['message' => 'Settings updated successfully.']);
    }
}
