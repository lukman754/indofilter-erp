<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CashAccountController;
use App\Http\Controllers\Api\CashTransactionController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\PartnerController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::apiResource('/companies', CompanyController::class);
    Route::apiResource('/partners', PartnerController::class);
    Route::apiResource('/products', ProductController::class);
    Route::get('/documents/check-local-file', [DocumentController::class, 'checkLocalFileExists']);
    Route::get('/documents/form-dependencies', [DocumentController::class, 'formDependencies']);
    Route::get('/documents/generate-number', [DocumentController::class, 'generateNumber']);
    Route::post('/documents/parse-pdf', [DocumentController::class, 'parsePdf']);
    Route::apiResource('/documents', DocumentController::class);
    Route::post('/documents/{document}/upload-po', [DocumentController::class, 'uploadPoFile']);
    Route::get('/documents/{document}/download-po', [DocumentController::class, 'downloadPoFile']);
    Route::post('/documents/{document}/confirm', [DocumentController::class, 'confirm']);
    Route::post('/documents/{document}/cancel', [DocumentController::class, 'cancel']);
    Route::get('/documents/{id}/export', [DocumentController::class, 'exportDocx']);

    Route::get('/settings', [SettingController::class, 'getSettings']);
    Route::post('/settings', [SettingController::class, 'updateSettings']);

    Route::get('/global-search', [SearchController::class, 'globalSearch']);

    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Kas
    Route::apiResource('/cash-accounts', CashAccountController::class)->except(['show']);
    Route::get('/cash-transactions/summary', [CashTransactionController::class, 'summary']);
    Route::apiResource('/cash-transactions', CashTransactionController::class)->except(['show']);
});

