<?php

use App\Http\Controllers\Web\AuthWebController;
use App\Http\Controllers\Web\CustomerWebController;
use App\Http\Controllers\Web\DashboardController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Browser-based session authentication and tenant dashboard routes.
|
*/

// Entry point: redirect to dashboard if logged in, else login
Route::get('/', function () {
    return Auth::check() ? redirect()->route('dashboard') : redirect()->route('login');
});

// -------------------------------------------------------------------------
// Guest Web Routes
// -------------------------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthWebController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthWebController::class, 'login'])->name('login.post');
});

// -------------------------------------------------------------------------
// Protected Authenticated Web Routes
// -------------------------------------------------------------------------
Route::middleware(['auth', 'tenant.identify', 'tenant.active'])->group(function () {
    Route::post('/logout', [AuthWebController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::resource('customers', CustomerWebController::class);
});
