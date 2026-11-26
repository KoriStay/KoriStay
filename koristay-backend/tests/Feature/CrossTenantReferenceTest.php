<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function bookingAttributes(Unit $unit): array
{
    return [
        'unit_id' => $unit->id,
        'booking_code' => fake()->unique()->bothify('KS-####'),
        'guest_name' => 'Tamu',
        'guest_email' => 'tamu@example.com',
        'check_in' => '2026-12-01',
        'check_out' => '2026-12-03',
        'total_price' => 200000,
    ];
}

beforeEach(function () {
    $this->tenantA = Tenant::create(['name' => 'Villa A', 'slug' => 'villa-a']);
    $this->tenantB = Tenant::create(['name' => 'Villa B', 'slug' => 'villa-b']);

    app(TenantContext::class)->setTenant($this->tenantB);
    $this->unitB = Unit::create(['name' => 'Unit B', 'slug' => 'unit-b', 'base_price' => 100000]);
    $this->bookingB = Booking::create(bookingAttributes($this->unitB));

    app(TenantContext::class)->setTenant($this->tenantA);
    $this->unitA = Unit::create(['name' => 'Unit A', 'slug' => 'unit-a', 'base_price' => 100000]);
});

it('allows a booking for a unit of the same tenant', function () {
    $booking = Booking::create(bookingAttributes($this->unitA));

    expect($booking->tenant_id)->toBe($this->tenantA->id);
});

it('rejects a booking that points at a unit of another tenant', function () {
    Booking::create(bookingAttributes($this->unitB));
})->throws(QueryException::class);

it('rejects moving a booking to a unit of another tenant', function () {
    $booking = Booking::create(bookingAttributes($this->unitA));

    $booking->update(['unit_id' => $this->unitB->id]);
})->throws(QueryException::class);

it('allows a payment for a booking of the same tenant', function () {
    $booking = Booking::create(bookingAttributes($this->unitA));

    $payment = Payment::create(['booking_id' => $booking->id, 'gateway' => 'test', 'amount' => 200000]);

    expect($payment->tenant_id)->toBe($this->tenantA->id);
});

it('rejects a payment that points at a booking of another tenant', function () {
    Payment::create(['booking_id' => $this->bookingB->id, 'gateway' => 'test', 'amount' => 200000]);
})->throws(QueryException::class);
