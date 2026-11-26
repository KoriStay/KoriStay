<?php

use App\Actions\Auth\GetCurrentUser;
use App\Actions\Auth\LoginUser;
use App\Actions\Auth\LogoutUser;
use App\Actions\Booking\CancelBooking;
use App\Actions\Booking\CheckInBooking;
use App\Actions\Booking\CompleteBooking;
use App\Actions\Booking\ConfirmBooking;
use App\Actions\Booking\CreateBooking;
use App\Actions\Booking\CreateErpBooking;
use App\Actions\Booking\ListBookings;
use App\Actions\Booking\LookUpBooking;
use App\Actions\Booking\ShowBooking;
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
    Route::get('/bookings', ListBookings::class)->middleware('permission:bookings.view');
    Route::post('/bookings', CreateErpBooking::class)->middleware('permission:bookings.create');

    Route::prefix('bookings/{booking}')->whereNumber('booking')->group(function () {
        Route::get('/', ShowBooking::class)->middleware('permission:bookings.view');
        Route::post('/confirm', ConfirmBooking::class)->middleware('permission:bookings.update');
        Route::post('/check-in', CheckInBooking::class)->middleware('permission:bookings.check-in');
        Route::post('/complete', CompleteBooking::class)->middleware('permission:bookings.check-in');
        Route::post('/cancel', CancelBooking::class)->middleware('permission:bookings.cancel');
    });
});

Route::prefix('public')->middleware(['tenant.required'])->group(function () {
    Route::get('/units', ListPublishedUnits::class);
    Route::post('/bookings', CreateBooking::class);
    Route::post('/bookings/lookup', LookUpBooking::class)->middleware('throttle:booking-lookup');
});
