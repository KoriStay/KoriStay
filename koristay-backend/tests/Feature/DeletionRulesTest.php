<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Villa A', 'slug' => 'villa-a']);
    app(TenantContext::class)->setTenant($this->tenant);

    $this->unit = Unit::create(['name' => 'Unit A', 'slug' => 'unit-a', 'base_price' => 100000]);
    $this->staff = User::create([
        'name' => 'Staff A',
        'email' => 'staff-a@example.com',
        'password' => 'password123',
        'tenant_id' => $this->tenant->id,
    ]);
});

function bookUnit(Unit $unit): Booking
{
    $booking = new Booking([
        'unit_id' => $unit->id,
        'booking_code' => 'KS-0001',
        'guest_name' => 'Tamu',
        'guest_email' => 'tamu@example.com',
        'check_in' => '2026-12-01',
        'check_out' => '2026-12-03',
        'guests' => 2,
    ]);
    $booking->total_price = 200000;
    $booking->save();

    return $booking;
}

it('refuses to delete a tenant that has bookings', function () {
    bookUnit($this->unit);

    $this->tenant->delete();
})->throws(QueryException::class);

it('refuses to delete a unit that has bookings', function () {
    bookUnit($this->unit);

    $this->unit->delete();
})->throws(QueryException::class);

it('refuses to delete a booking that has payments', function () {
    $booking = bookUnit($this->unit);
    $payment = new Payment(['booking_id' => $booking->id, 'gateway' => 'test']);
    $payment->amount = 200000;
    $payment->save();

    $booking->delete();
})->throws(QueryException::class);

it('removes the units and staff of a tenant without bookings', function () {
    $this->tenant->delete();

    expect(Unit::withoutGlobalScope(TenantScope::class)->whereKey($this->unit->id)->exists())->toBeFalse()
        ->and(User::whereKey($this->staff->id)->exists())->toBeFalse();
});
