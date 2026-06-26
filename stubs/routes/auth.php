<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

if (!app()->isProduction()) {
    Route::get('/test', [TestController::class, 'index']);
}

Route::middleware(['throttle:6,1'])->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
    Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware(['signed'])
        ->name('verification.verify');
});
Route::post('/login', [AuthController::class, 'login'])->middleware(['throttle:auth-login'])->name('login');

Route::middleware(['auth:sanctum', 'throttle:6,1'])->group(function () {
    Route::post('/email/verification-notification', [AuthController::class, 'sendVerificationEmail'])
        ->name('verification.send');

    Route::get('/sessions', [AuthController::class, 'sessions'])->name('sessions.index');
    Route::delete('/sessions/{id}', [AuthController::class, 'revokeSession'])->name('sessions.destroy');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');

});

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/user', [UserController::class, 'show'])->name('user.show');
    Route::patch('/user', [UserController::class, 'update'])->name('user.update');
});

