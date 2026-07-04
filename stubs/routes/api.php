<?php

use App\Http\Controllers\TestController;
use App\Http\Controllers\UserController;

if (!app()->isProduction()) {
    Route::get('/test', [TestController::class, 'index']);
    Route::post('/test', [TestController::class, 'store']);
}

Route::middleware(['auth:sanctum', 'user', 'verified'])->group(function () {
    Route::get('/user', [UserController::class, 'show'])->name('user.show');
    Route::patch('/user', [UserController::class, 'update'])->name('user.update');
});
