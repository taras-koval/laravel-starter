<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// This route file is loaded through the API middleware group, including "throttle:api" (60/min per user or IP).

Route::post('/register', [AuthController::class, 'register'])
    ->middleware(['throttle:6,1,register'])
    ->name('register');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware(['throttle:6,1,forgot-password'])
    ->name('forgot-password');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware(['throttle:6,1,reset-password'])
    ->name('reset-password');
Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed', 'throttle:6,1,verify-email'])
    ->name('verification.verify');

Route::post('/login', [AuthController::class, 'login'])->middleware(['throttle:auth-login'])->name('login');

Route::middleware(['auth:sanctum', 'user'])->group(function () {
    Route::post('/email/verification-notification', [AuthController::class, 'sendVerificationEmail'])
        ->middleware(['throttle:6,1,verification-send'])
        ->name('verification.send');

    Route::get('/sessions', [AuthController::class, 'sessions'])->name('sessions.index');
    Route::delete('/sessions/{id}', [AuthController::class, 'revokeSession'])->name('sessions.destroy');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
});
