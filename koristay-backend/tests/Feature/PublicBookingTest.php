<?php

use App\Actions\Booking\CreateBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-12-01 03:00:00'); // 11:00 WITA

    $this->tenantA = Tenant::create(['name' => 'Villa A', 'slug' => 'villa-a']);
    $this->tenantB = Tenant::create(['name' => 'Villa B', 'slug' => 'villa-b']);

    app(TenantContext::class)->setTenant($this->tenantB);
    $this->unitB = Unit::create(['name' => 'Unit B', 'slug' => 'unit-b', 'base_price' => 900000]);

    app(TenantContext::class)->setTenant($this->tenantA);
    $this->unit = Unit::create(['name' => 'Unit A', 'slug' => 'unit-a', 'base_price' => 1500000, 'max_guests' => 4]);
    $this->hidden = Unit::create(['name' => 'Hidden', 'slug' => 'hidden', 'base_price' => 1, 'is_published' => false]);

    app()->forgetInstance(TenantContext::class);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function bookingRequest(array $overrides = []): array
{
    return [
        'unit_id' => test()->unit->id,
        'check_in' => '2026-12-10',
        'check_out' => '2026-12-13',
        'guests' => 2,
        'guest_name' => 'Ayu Lestari',
        'guest_email' => 'ayu@example.com',
        'guest_phone' => '+6281234567890',
        ...$overrides,
    ];
}

function postBooking(array $overrides = []): TestResponse
{
    return test()->postJson('/api/public/bookings', bookingRequest($overrides), ['X-Tenant' => 'villa-a']);
}

it('creates a pending villa_web booking priced by the server', function () {
    $response = postBooking(['total_price' => 1, 'status' => 'confirmed', 'source' => 'marketplace'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.total_price', 4500000)
        ->assertJsonPath('data.check_in', '2026-12-10')
        ->assertJsonPath('data.check_out', '2026-12-13')
        ->assertJsonPath('data.guests', 2);

    expect($response->json('data.booking_code'))->toMatch('/^KS-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/')
        ->and(array_keys($response->json('data')))->toBe([
            'booking_code', 'status', 'unit_id', 'check_in', 'check_out', 'guests',
            'guest_name', 'guest_email', 'guest_phone', 'total_price', 'expires_at',
        ]);

    $booking = Booking::withoutGlobalScope(TenantScope::class)->firstWhere('booking_code', $response->json('data.booking_code'));

    expect($booking->tenant_id)->toBe($this->tenantA->id)
        ->and($booking->source)->toBe(BookingSource::VILLA_WEB)
        ->and($booking->expires_at->equalTo(now()->addHour()))->toBeTrue();
});

it('rejects a unit of another tenant or an unpublished unit', function (string $unit) {
    postBooking(['unit_id' => $this->{$unit}->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('unit_id');
})->with(['unitB', 'hidden']);

it('rejects a check in that is already past in Bali time', function () {
    $this->travelTo('2026-12-01 17:00:00'); // still 1 Dec in UTC, already 2 Dec 01:00 WITA

    postBooking(['check_in' => '2026-12-01', 'check_out' => '2026-12-03'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('check_in');

    postBooking(['check_in' => '2026-12-02', 'check_out' => '2026-12-03'])->assertCreated();
});

it('rejects a check out that is not after the check in', function (string $checkOut) {
    postBooking(['check_in' => '2026-12-10', 'check_out' => $checkOut])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('check_out');
})->with(['2026-12-10', '2026-12-09']);

it('rejects more guests than the unit allows', function () {
    postBooking(['guests' => 5])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('guests');
});

it('requires the guest details', function () {
    $this->postJson('/api/public/bookings', [], ['X-Tenant' => 'villa-a'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unit_id', 'check_in', 'check_out', 'guests', 'guest_name', 'guest_email']);
});

it('cancels an expired pending booking on the unit before taking its dates', function () {
    $first = postBooking()->assertCreated()->json('data.booking_code');

    $this->travel(61)->minutes();

    postBooking(['guest_email' => 'second@example.com'])->assertCreated();

    expect(Booking::withoutGlobalScope(TenantScope::class)->firstWhere('booking_code', $first)->status)
        ->toBe(BookingStatus::CANCELLED);
});

it('answers 409 when the dates are already taken', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('The overlap constraint exists on Postgres only.');
    }

    postBooking()->assertCreated();

    postBooking(['check_in' => '2026-12-12', 'check_out' => '2026-12-15', 'guest_email' => 'late@example.com'])
        ->assertStatus(409)
        ->assertJsonValidationErrors('check_in');
});

it('confirms erp_manual bookings right away without a hold', function () {
    app(TenantContext::class)->setTenant($this->tenantA);

    $booking = CreateBooking::run($this->unit, [
        'check_in' => '2026-12-20',
        'check_out' => '2026-12-22',
        'guests' => 2,
        'guest_name' => 'Walk In',
        'guest_email' => 'walkin@example.com',
    ], BookingSource::ERP_MANUAL);

    expect($booking->status)->toBe(BookingStatus::CONFIRMED)
        ->and($booking->source)->toBe(BookingSource::ERP_MANUAL)
        ->and($booking->expires_at)->toBeNull()
        ->and($booking->total_price)->toBe(3000000);
});
