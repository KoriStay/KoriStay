<?php

use App\Actions\Auth\GetCurrentUser;
use App\Actions\Auth\LoginUser;
use App\Actions\Auth\LogoutUser;
use App\Actions\Booking\CreateBooking;
use App\Actions\Unit\ListPublishedUnits;
use App\Actions\Unit\ListUnits;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', LoginUser::class)->middleware('throttle:login')->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', GetCurrentUser::class)->name('user');
        Route::post('/logout', LogoutUser::class)->name('logout');
    });
});

Route::prefix('erp')->middleware(['auth:sanctum', 'tenant.required', 'tenant.member'])->group(function () {
    Route::get('/units', ListUnits::class)->middleware('permission:units.view');
});

Route::prefix('public')->middleware(['tenant.required'])->group(function () {
    Route::get('/units', ListPublishedUnits::class);
    Route::post('/bookings', CreateBooking::class);
});
