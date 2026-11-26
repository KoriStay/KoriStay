<?php

use App\Models\Booking;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function bookingWithoutCode(Unit $unit, int $week): Booking
{
    $checkIn = CarbonImmutable::parse('2027-03-01')->addWeeks($week);

    $booking = new Booking([
        'unit_id' => $unit->id,
        'guest_name' => 'Tamu',
        'guest_email' => 'tamu@example.com',
        'check_in' => $checkIn->toDateString(),
        'check_out' => $checkIn->addDays(2)->toDateString(),
        'guests' => 2,
    ]);
    $booking->total_price = 200000;

    return $booking;
}

beforeEach(function () {
    app(TenantContext::class)->setTenant(Tenant::create(['name' => 'Villa A', 'slug' => 'villa-a']));
    $this->unit = Unit::create(['name' => 'Unit A', 'slug' => 'unit-a', 'base_price' => 100000]);
});

it('gives a new booking a KS code without ambiguous characters', function () {
    $booking = bookingWithoutCode($this->unit, 0);
    $booking->save();

    expect($booking->fresh()->booking_code)->toMatch('/^KS-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/');
});

it('gives every booking a different code', function () {
    $codes = collect(range(0, 19))->map(function (int $week) {
        $booking = bookingWithoutCode($this->unit, $week);
        $booking->save();

        return $booking->booking_code;
    });

    expect($codes->unique())->toHaveCount(20);
});

it('keeps a code that the server set explicitly', function () {
    $booking = bookingWithoutCode($this->unit, 0);
    $booking->booking_code = 'KS-ERP234';
    $booking->save();

    expect($booking->fresh()->booking_code)->toBe('KS-ERP234');
});

it('generates codes in the agreed format', function () {
    $codes = collect(range(1, 200))->map(fn () => Booking::generateCode());

    expect($codes->every(fn (string $code) => preg_match('/^KS-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/', $code) === 1))->toBeTrue();
});
