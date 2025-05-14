<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReceiptImageController;


Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);


Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::prefix('receipts')->group(function () {
        // Process a single receipt image with AI
        Route::post('/images/process-ai', [ReceiptImageController::class, 'processWithAi'])
            ->name('receipt.images.process-ai');
   
    });
    

    Route::post('/logout', [AuthController::class, 'logout']);
});