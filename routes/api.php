<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Middleware\EnsureAdminKey;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ---- Auth ----
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    // ---- Razorpay webhook (public, signature-verified) ----
    Route::post('webhooks/razorpay', WebhookController::class);

    // ---- Authenticated user endpoints ----
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::post('payments/orders', [PaymentController::class, 'createOrder'])->middleware('throttle:30,1');
        Route::post('payments/verify', [PaymentController::class, 'verify'])->middleware('throttle:30,1');
        Route::get('payments', [PaymentController::class, 'index']);
        Route::get('payments/{id}', [PaymentController::class, 'show'])->whereNumber('id');
    });

    // ---- Admin endpoints (X-Admin-Key header) ----
    Route::middleware(EnsureAdminKey::class)->group(function () {
        Route::post('admin/payments/{payment}/refund', [PaymentController::class, 'refund'])->whereNumber('payment');
    });
});
