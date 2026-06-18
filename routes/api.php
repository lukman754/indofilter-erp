<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\PartnerController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::apiResource('/companies', CompanyController::class);
    Route::apiResource('/partners', PartnerController::class);
    Route::apiResource('/products', ProductController::class);
    Route::get('/documents/generate-number', [DocumentController::class, 'generateNumber']);
    Route::apiResource('/documents', DocumentController::class);
    Route::post('/documents/{document}/confirm', [DocumentController::class, 'confirm']);
    Route::post('/documents/{document}/cancel', [DocumentController::class, 'cancel']);
    Route::get('/documents/{id}/export', [DocumentController::class, 'exportDocx']);
    Route::post('/documents/{document}/sync-storage', [DocumentController::class, 'syncSupabase']);

    Route::get('/settings/supabase', [\App\Http\Controllers\Api\SettingController::class, 'getSupabaseSettings']);
    Route::post('/settings/supabase', [\App\Http\Controllers\Api\SettingController::class, 'updateSupabaseSettings']);
    Route::post('/settings/supabase/test', [\App\Http\Controllers\Api\SettingController::class, 'testSupabaseConnection']);

    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
});
