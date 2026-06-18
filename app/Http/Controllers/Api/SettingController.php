<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SupabaseStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function getSupabaseSettings(): JsonResponse
    {
        return response()->json([
            'supabase_enabled'     => Setting::get('supabase_enabled', '0'),
            'supabase_url'         => Setting::get('supabase_url', ''),
            'supabase_service_key' => Setting::get('supabase_service_key', ''),
            'supabase_bucket'      => Setting::get('supabase_bucket', 'documents'),
        ]);
    }

    public function updateSupabaseSettings(Request $request): JsonResponse
    {
        $request->validate([
            'supabase_enabled'     => 'required|in:0,1',
            'supabase_url'         => 'nullable|string|url',
            'supabase_service_key' => 'nullable|string',
            'supabase_bucket'      => 'nullable|string|max:100',
        ]);

        Setting::set('supabase_enabled', $request->input('supabase_enabled', '0'));
        Setting::set('supabase_url', rtrim($request->input('supabase_url', ''), '/'));
        Setting::set('supabase_service_key', $request->input('supabase_service_key', ''));
        Setting::set('supabase_bucket', $request->input('supabase_bucket', 'documents'));

        return response()->json(['message' => 'Pengaturan Supabase Storage berhasil disimpan.']);
    }

    public function testSupabaseConnection(Request $request): JsonResponse
    {
        $request->validate([
            'supabase_url'         => 'required|string',
            'supabase_service_key' => 'required|string',
            'supabase_bucket'      => 'required|string',
        ]);

        $service = new SupabaseStorageService();
        $result  = $service->testConnection(
            $request->supabase_url,
            $request->supabase_service_key,
            $request->supabase_bucket
        );

        return response()->json($result);
    }
}
