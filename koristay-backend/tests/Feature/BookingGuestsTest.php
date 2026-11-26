<?php

use App\Models\Booking;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function bookingWithGuests(Unit $unit, ?int $guests): Booking
{
    $booking = new Booking([
        'unit_id' => $unit->id,
        'guest_name' => 'Tamu',
        'guest_email' => 'tamu@example.com',
        'check_in' => '2026-12-01',
        'check_out' => '2026-12-03',
        'guests' => $guests,
    ]);
    $booking->total_price = 200000;

    return $booking;
}

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Villa A', 'slug' => 'villa-a']);
    app(TenantContext::class)->setTenant($this->tenant);

    $this->bigUnit = Unit::create(['name' => 'Big', 'slug' => 'big', 'base_price' => 100000, 'max_guests' => 4]);
    $this->smallUnit = Unit::create(['name' => 'Small', 'slug' => 'small', 'base_price' => 100000, 'max_guests' => 2]);
});

it('accepts a booking with as many guests as the unit allows', function () {
    $booking = bookingWithGuests($this->bigUnit, 4);
    $booking->save();

    expect($booking->fresh()->guests)->toBe(4);
});

it('rejects a booking without guests', function (?int $guests) {
    bookingWithGuests($this->bigUnit, $guests)->save();
})->with([null, 0])->throws(ValidationException::class);

it('rejects a booking with more guests than the unit allows', function () {
    bookingWithGuests($this->bigUnit, 5)->save();
})->throws(ValidationException::class);

it('rejects raising the guests of a booking above the unit capacity', function () {
    $booking = bookingWithGuests($this->bigUnit, 2);
    $booking->save();

    $booking->update(['guests' => 5]);
})->throws(ValidationException::class);

it('rejects moving a booking to a unit that is too small', function () {
    $booking = bookingWithGuests($this->bigUnit, 3);
    $booking->save();

    $booking->update(['unit_id' => $this->smallUnit->id]);
})->throws(ValidationException::class);

it('checks the capacity without a tenant context when the tenant is set explicitly', function () {
    app()->forgetInstance(TenantContext::class);

    $booking = bookingWithGuests($this->bigUnit, 5);
    $booking->tenant_id = $this->tenant->id;

    expect(fn () => $booking->save())->toThrow(ValidationException::class);

    $booking->guests = 4;
    $booking->save();

    expect($booking->exists)->toBeTrue();
});
