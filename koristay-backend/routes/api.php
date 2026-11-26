<?php

use App\Actions\Auth\GetCurrentUser;
use App\Actions\Auth\LoginUser;
use App\Actions\Auth\LogoutUser;
use App\Models\Unit;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', LoginUser::class)->middleware('throttle:login')->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', GetCurrentUser::class)->name('user');
        Route::post('/logout', LogoutUser::class)->name('logout');
    });
});

Route::prefix('erp')->middleware(['auth:sanctum', 'tenant.required', 'tenant.member'])->group(function () {
    Route::get('/units', fn () => Unit::all())->middleware('permission:units.view');
});

Route::prefix('public')->middleware(['tenant.required'])->group(function () {
    Route::get('/units', fn () => Unit::where('is_published', true)->get());
});
