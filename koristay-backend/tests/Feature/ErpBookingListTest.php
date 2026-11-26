<?php

use App\Actions\Booking\CreateBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function seedBooking(Unit $unit, string $checkIn, string $checkOut, BookingStatus $status = BookingStatus::PENDING): Booking
{
    $booking = CreateBooking::run($unit, [
        'check_in' => $checkIn,
        'check_out' => $checkOut,
        'guests' => 1,
        'guest_name' => 'Tamu',
        'guest_email' => 'tamu@example.com',
    ], BookingSource::VILLA_WEB);

    if ($status !== BookingStatus::PENDING) {
        $booking->status = $status;
        $booking->save();
    }

    return $booking;
}

beforeEach(function () {
    $this->travelTo('2026-12-01 03:00:00');
    $this->seed(RoleAndPermissionSeeder::class);

    $this->tenantA = Tenant::create(['name' => 'Villa A', 'slug' => 'villa-a']);
    $this->tenantB = Tenant::create(['name' => 'Villa B', 'slug' => 'villa-b']);

    app(TenantContext::class)->setTenant($this->tenantB);
    seedBooking(Unit::create(['name' => 'B', 'slug' => 'b', 'base_price' => 100000]), '2026-12-05', '2026-12-06');

    app(TenantContext::class)->setTenant($this->tenantA);
    $this->unit1 = Unit::create(['name' => 'One', 'slug' => 'one', 'base_price' => 100000]);
    $this->unit2 = Unit::create(['name' => 'Two', 'slug' => 'two', 'base_price' => 100000]);
    $this->early = seedBooking($this->unit1, '2026-12-03', '2026-12-05', BookingStatus::CONFIRMED);
    $this->middle = seedBooking($this->unit1, '2026-12-10', '2026-12-12');
    $this->late = seedBooking($this->unit2, '2026-12-20', '2026-12-22', BookingStatus::CANCELLED);

    $this->staff = User::forceCreate([
        'name' => 'Front Desk',
        'email' => 'fd@example.com',
        'password' => 'password123',
        'tenant_id' => $this->tenantA->id,
    ]);
    $this->staff->assignRole('front_desk');

    app()->forgetInstance(TenantContext::class);
    setPermissionsTeamId(null);
    Sanctum::actingAs(User::find($this->staff->id));
});

/**
 * @return array<int, string>
 */
function listedCodes(string $query = ''): array
{
    return collect(test()->getJson("/api/erp/bookings{$query}")->assertOk()->json('data'))->pluck('booking_code')->all();
}

it('lists the bookings of the own tenant, latest check in first, with the erp fields', function () {
    $response = $this->getJson('/api/erp/bookings')
        ->assertOk()
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.per_page', 20);

    expect(collect($response->json('data'))->pluck('booking_code')->all())
        ->toBe([$this->late->booking_code, $this->middle->booking_code, $this->early->booking_code])
        ->and($response->json('data.0'))->toHaveKeys(['id', 'source', 'booking_code', 'status', 'total_price']);
});

it('filters by one or more statuses', function () {
    expect(listedCodes('?filter[status]=confirmed'))->toBe([$this->early->booking_code])
        ->and(listedCodes('?filter[status]=pending,confirmed'))->toBe([$this->middle->booking_code, $this->early->booking_code]);
});

it('filters by unit', function () {
    expect(listedCodes("?filter[unit_id]={$this->unit2->id}"))->toBe([$this->late->booking_code]);
});

it('filters by a check in range', function () {
    expect(listedCodes('?filter[check_in_from]=2026-12-04&filter[check_in_to]=2026-12-20'))
        ->toBe([$this->late->booking_code, $this->middle->booking_code]);
});

it('sorts by check in or creation time when asked', function () {
    expect(listedCodes('?sort=check_in'))
        ->toBe([$this->early->booking_code, $this->middle->booking_code, $this->late->booking_code]);
});

it('rejects unknown filters and sorts', function (string $query) {
    $this->getJson("/api/erp/bookings{$query}")->assertStatus(400);
})->with(['?filter[guest_email]=x', '?sort=total_price']);

it('rejects a check in filter that is not a date', function () {
    $this->getJson('/api/erp/bookings?filter[check_in_from]=yesterday')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('filter.check_in_from');
});

it('forbids staff without bookings.view', function () {
    $noRole = User::forceCreate([
        'name' => 'No Role',
        'email' => 'norole@example.com',
        'password' => 'password123',
        'tenant_id' => $this->tenantA->id,
    ]);
    Sanctum::actingAs(User::find($noRole->id));

    $this->getJson('/api/erp/bookings')->assertForbidden();
});
