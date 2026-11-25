<?php

use Illuminate\Support\Facades\Route;
use App\Actions\Auth\LoginUser;
use App\Actions\Auth\GetCurrentUser;
use App\Actions\Auth\LogoutUser;
use App\Models\Unit;

Route::prefix('auth')->group(function () {
    Route::post('/login', LoginUser::class)->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', GetCurrentUser::class)->name('user');
        Route::post('/logout', LogoutUser::class)->name('logout');
    });
});

Route::prefix('erp')->middleware(['auth:sanctum', 'tenant.required'])->group(function () { 
    Route::get('/units', fn() => Unit::all());
});

Route::prefix('public')->middleware(['tenant.required'])->group(function () {
    Route::get('/units', fn() => Unit::where('is_published', true)->get());
});