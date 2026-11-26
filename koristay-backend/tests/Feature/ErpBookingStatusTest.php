<?php

use App\Actions\Booking\CancelExpiredPendingBookings;
use App\Actions\Booking\CreateBooking;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function bookingWithStatus(Unit $unit, BookingStatus $status, int $week): Booking
{
    $checkIn = now()->addWeeks($week + 1)->toDateString();

    $booking = CreateBooking::run($unit, [
        'check_in' => $checkIn,
        'check_out' => now()->addWeeks($week + 1)->addDays(2)->toDateString(),
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

function actAs(Tenant $tenant, string $role): void
{
    $user = User::forceCreate([
        'name' => $role,
        'email' => "{$role}-".uniqid().'@example.com',
        'password' => 'password123',
        'tenant_id' => $tenant->id,
    ]);
    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);
    setPermissionsTeamId(null);

    Sanctum::actingAs(User::find($user->id));
}

function freshStatus(Booking $booking): BookingStatus
{
    return Booking::withoutGlobalScope(TenantScope::class)->findOrFail($booking->id)->status;
}

beforeEach(function () {
    $this->freezeSecond();
    $this->seed(RoleAndPermissionSeeder::class);

    $this->tenantB = Tenant::create(['name' => 'Villa B', 'slug' => 'villa-b']);
    app(TenantContext::class)->setTenant($this->tenantB);
    $this->otherTenantBooking = bookingWithStatus(Unit::create(['name' => 'B', 'slug' => 'b', 'base_price' => 100000]), BookingStatus::PENDING, 0);

    $this->tenant = Tenant::create(['name' => 'Villa A', 'slug' => 'villa-a']);
    app(TenantContext::class)->setTenant($this->tenant);
    $this->unit = Unit::create(['name' => 'A', 'slug' => 'a', 'base_price' => 100000]);

    $this->pending = bookingWithStatus($this->unit, BookingStatus::PENDING, 0);
    $this->confirmed = bookingWithStatus($this->unit, BookingStatus::CONFIRMED, 1);
    $this->checkedIn = bookingWithStatus($this->unit, BookingStatus::CHECKED_IN, 2);
    $this->completed = bookingWithStatus($this->unit, BookingStatus::COMPLETED, 3);
    $this->cancelled = bookingWithStatus($this->unit, BookingStatus::CANCELLED, 4);

    app()->forgetInstance(TenantContext::class);
    setPermissionsTeamId(null);
});

it('shows one booking of the own tenant to any staff role', function () {
    actAs($this->tenant, 'housekeeping');

    $this->getJson("/api/erp/bookings/{$this->confirmed->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $this->confirmed->id)
        ->assertJsonPath('data.status', 'confirmed');
});

it('hides bookings of other tenants and non numeric ids', function (string $method, string $path) {
    actAs($this->tenant, 'owner');

    $this->json($method, str_replace('{other}', (string) $this->otherTenantBooking->id, $path))->assertNotFound();

    expect(freshStatus($this->otherTenantBooking))->toBe(BookingStatus::PENDING);
})->with([
    'show' => ['GET', '/api/erp/bookings/{other}'],
    'confirm' => ['POST', '/api/erp/bookings/{other}/confirm'],
    'cancel' => ['POST', '/api/erp/bookings/{other}/cancel'],
    'non numeric' => ['GET', '/api/erp/bookings/KS-AAAAAA'],
]);

it('confirms a pending booking and stops its hold from expiring', function () {
    actAs($this->tenant, 'front_desk');

    $this->postJson("/api/erp/bookings/{$this->pending->id}/confirm")
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.expires_at', null);

    $this->travel(2)->hours();
    CancelExpiredPendingBookings::run();

    expect(freshStatus($this->pending))->toBe(BookingStatus::CONFIRMED);
});

it('moves a booking forward along the allowed path', function (string $from, string $action, string $to) {
    actAs($this->tenant, 'owner');

    $this->postJson("/api/erp/bookings/{$this->{$from}->id}/{$action}")
        ->assertOk()
        ->assertJsonPath('data.status', $to);

    expect(freshStatus($this->{$from})->value)->toBe($to);
})->with([
    'confirmed to checked_in' => ['confirmed', 'check-in', 'checked_in'],
    'checked_in to completed' => ['checkedIn', 'complete', 'completed'],
    'pending to cancelled' => ['pending', 'cancel', 'cancelled'],
    'confirmed to cancelled' => ['confirmed', 'cancel', 'cancelled'],
]);

it('answers 409 for a transition that the flow does not allow', function (string $from, string $action) {
    actAs($this->tenant, 'owner');
    $before = freshStatus($this->{$from});

    $this->postJson("/api/erp/bookings/{$this->{$from}->id}/{$action}")
        ->assertStatus(409)
        ->assertJsonPath('status', $before->value);

    expect(freshStatus($this->{$from}))->toBe($before);
})->with([
    'check in a pending booking' => ['pending', 'check-in'],
    'complete a confirmed booking' => ['confirmed', 'complete'],
    'cancel a checked in booking' => ['checkedIn', 'cancel'],
    'confirm a confirmed booking' => ['confirmed', 'confirm'],
    'confirm a cancelled booking' => ['cancelled', 'confirm'],
    'cancel a completed booking' => ['completed', 'cancel'],
]);

it('checks the permission of each action', function (string $action, string $from) {
    actAs($this->tenant, 'housekeeping');

    $this->postJson("/api/erp/bookings/{$this->{$from}->id}/{$action}")->assertForbidden();

    expect(freshStatus($this->{$from}))->not->toBe(BookingStatus::CANCELLED);
})->with([
    ['confirm', 'pending'],
    ['check-in', 'confirmed'],
    ['complete', 'checkedIn'],
    ['cancel', 'pending'],
]);
