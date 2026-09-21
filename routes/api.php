<?php

use App\Http\Controllers\Api\V1\AuthApiController;
use App\Http\Controllers\Api\V1\CustomerApiController;
use App\Http\Controllers\Api\V1\OrderApiController;
use App\Http\Controllers\Api\V1\ProductApiController;
use App\Http\Controllers\Api\V1\UserApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - V1
|--------------------------------------------------------------------------
|
| Versioned REST API endpoints for the multi-tenant SaaS platform.
|
*/

Route::prefix('v1')->group(function () {

    // -------------------------------------------------------------
    // Public Authentication
    // -------------------------------------------------------------
    Route::post('/auth/login', [AuthApiController::class, 'login'])->name('api.v1.auth.login');

    // -------------------------------------------------------------
    // Protected Endpoints (Requires Sanctum Token)
    // -------------------------------------------------------------
    Route::middleware('auth:sanctum')->group(function () {

        // Session management
        Route::post('/auth/logout', [AuthApiController::class, 'logout'])->name('api.v1.auth.logout');
        Route::get('/auth/me', [AuthApiController::class, 'me'])->name('api.v1.auth.me');

        // ---------------------------------------------------------
        // Tenant-Scoped Management Routes
        // Pipeline: Sanctum Auth -> Tenant Resolution -> Operational Status Check
        // ---------------------------------------------------------
        Route::middleware(['tenant.identify', 'tenant.active'])->group(function () {
            Route::apiResource('users', UserApiController::class)->names('api.v1.users');
            Route::apiResource('customers', CustomerApiController::class)->names('api.v1.customers');
            Route::apiResource('products', ProductApiController::class)->names('api.v1.products');
            Route::apiResource('orders', OrderApiController::class)->names('api.v1.orders');
        });
    });
});
