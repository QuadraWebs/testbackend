<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReceiptImageController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\UserPreferenceController;


Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);


Route::middleware(['auth:sanctum'])->group(function () {
    Route::middleware('auth:sanctum')->get('/user', [UserPreferenceController::class, 'getUser']);

    Route::prefix('receipts')->group(function () {
        Route::post('/images/process-ai', [ReceiptImageController::class, 'processWithAi']);
        Route::post('/', [ReceiptController::class, 'storeFromJson']);
    });

    Route::prefix('dashboard')->group(function () {
        Route::get('/deductibility-summary', [App\Http\Controllers\DashboardController::class, 'getDeductibilitySummary']);
        Route::get('/tax-suggestions', [App\Http\Controllers\DashboardController::class, 'getTaxSuggestionsApi']);

    });


    
    Route::post('/user-preference', [UserPreferenceController::class, 'store']);
    Route::get('/user-preference', [UserPreferenceController::class, 'getUserPreferences']);

    Route::post('/logout', [AuthController::class, 'logout']);
});