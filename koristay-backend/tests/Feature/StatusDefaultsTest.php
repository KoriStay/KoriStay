<?php

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(TenantContext::class)->setTenant(Tenant::create(['name' => 'Villa A', 'slug' => 'villa-a']));
    $unit = Unit::create(['name' => 'Unit A', 'slug' => 'unit-a', 'base_price' => 100000]);

    $this->booking = new Booking([
        'unit_id' => $unit->id,
        'booking_code' => 'KS-0001',
        'guest_name' => 'Tamu',
        'guest_email' => 'tamu@example.com',
        'check_in' => '2026-12-01',
        'check_out' => '2026-12-03',
        'guests' => 2,
    ]);
    $this->booking->total_price = 200000;
    $this->booking->save();
});

it('gives a new booking the database defaults that match the status and source enums', function () {
    $booking = $this->booking->fresh();

    expect($booking->status)->toBe(BookingStatus::PENDING)
        ->and($booking->source)->toBe(BookingSource::VILLA_WEB);
});

it('gives a new payment the database default that matches the payment status enum', function () {
    $payment = new Payment(['booking_id' => $this->booking->id, 'gateway' => 'test']);
    $payment->amount = 200000;
    $payment->save();

    expect($payment->fresh()->status)->toBe(PaymentStatus::UNPAID);
});
