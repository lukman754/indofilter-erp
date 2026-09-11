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
            'po_masuk',
            'supporting_docs',
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
            'po_masuk',
            'supporting_docs',
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

    public function changeDriveLetter(Request $request): JsonResponse
    {
        $request->validate([
            'drive_letter' => 'required|string|alpha|size:1',
        ]);

        $newDrive = strtoupper($request->drive_letter);
        $settings = Setting::all();
        $updatedCount = 0;

        foreach ($settings as $setting) {
            $value = $setting->value;
            if ($value && preg_match('#^([a-zA-Z]):([/\\\\].*)$#', $value, $matches)) {
                $newValue = $newDrive . ':' . $matches[2];
                if ($value !== $newValue) {
                    $setting->value = $newValue;
                    $setting->save();
                    $updatedCount++;
                }
            }
        }

        $this->updateEnvDriveLetter($newDrive);

        return response()->json([
            'message' => "Huruf drive berhasil diubah menjadi {$newDrive} pada {$updatedCount} lokasi pengaturan dan file konfigurasi .env."
        ]);
    }

    private function updateEnvDriveLetter(string $newDrive): void
    {
        $envPath = base_path('.env');
        if (!file_exists($envPath)) {
            return;
        }

        $envContent = file_get_contents($envPath);
        
        $patterns = [
            '#^(DB_DATABASE=["\']?)[a-zA-Z](:[/\\][^"\']*)(["\']?)$#m' => '${1}' . $newDrive . '${2}${3}',
            '#^(DOCUMENTS_STORAGE_PATH=["\']?)[a-zA-Z](:[/\\][^"\']*)(["\']?)$#m' => '${1}' . $newDrive . '${2}${3}',
        ];

        $newContent = preg_replace(array_keys($patterns), array_values($patterns), $envContent);
        
        file_put_contents($envPath, $newContent);
    }
}
