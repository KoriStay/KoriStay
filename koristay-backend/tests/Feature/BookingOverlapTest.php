<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function stay(Unit $unit, string $checkIn, string $checkOut, ?BookingStatus $status = null): Booking
{
    $booking = new Booking([
        'unit_id' => $unit->id,
        'guest_name' => 'Tamu',
        'guest_email' => 'tamu@example.com',
        'check_in' => $checkIn,
        'check_out' => $checkOut,
        'guests' => 2,
    ]);
    $booking->total_price = 200000;

    if ($status !== null) {
        $booking->status = $status;
    }

    $booking->save();

    return $booking;
}

function expectSqlState(string $sqlState): Closure
{
    return fn (QueryException $exception) => expect($exception->getCode())->toBe($sqlState);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Exclusion and check constraints exist on Postgres only.');
    }

    app(TenantContext::class)->setTenant(Tenant::create(['name' => 'Villa A', 'slug' => 'villa-a']));
    $this->unit = Unit::create(['name' => 'Unit A', 'slug' => 'unit-a', 'base_price' => 100000]);
    $this->otherUnit = Unit::create(['name' => 'Unit B', 'slug' => 'unit-b', 'base_price' => 100000]);
});

it('rejects a second booking that overlaps a holding booking on the same unit', function (BookingStatus $holding) {
    stay($this->unit, '2026-12-10', '2026-12-13', $holding);

    expect(fn () => stay($this->unit, '2026-12-12', '2026-12-14'))->toThrow(expectSqlState('23P01'));
})->with([BookingStatus::PENDING, BookingStatus::CONFIRMED, BookingStatus::CHECKED_IN]);

it('accepts a booking that starts on the check out day of another', function () {
    stay($this->unit, '2026-12-10', '2026-12-12');

    expect(stay($this->unit, '2026-12-12', '2026-12-14')->exists)->toBeTrue();
});

it('does not let cancelled or completed bookings hold the unit', function (BookingStatus $released) {
    stay($this->unit, '2026-12-10', '2026-12-13', $released);

    expect(stay($this->unit, '2026-12-10', '2026-12-13')->exists)->toBeTrue();
})->with([BookingStatus::CANCELLED, BookingStatus::COMPLETED]);

it('accepts the same dates on another unit', function () {
    stay($this->unit, '2026-12-10', '2026-12-13');

    expect(stay($this->otherUnit, '2026-12-10', '2026-12-13')->exists)->toBeTrue();
});

it('rejects reactivating a cancelled booking over dates that were taken since', function () {
    $cancelled = stay($this->unit, '2026-12-10', '2026-12-13', BookingStatus::CANCELLED);
    stay($this->unit, '2026-12-11', '2026-12-12');

    $cancelled->status = BookingStatus::PENDING;

    expect(fn () => $cancelled->save())->toThrow(expectSqlState('23P01'));
});

it('rejects a stay of zero or negative nights', function (string $checkOut) {
    expect(fn () => stay($this->unit, '2026-12-10', $checkOut))->toThrow(expectSqlState('23514'));
})->with(['2026-12-10', '2026-12-09']);
