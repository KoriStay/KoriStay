<?php

use App\Actions\Booking\CreateBooking;
use App\Enums\BookingSource;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.debug' => false]);
    $this->seed(RoleAndPermissionSeeder::class);

    $other = Tenant::create(['name' => 'Villa B', 'slug' => 'villa-b']);
    app(TenantContext::class)->setTenant($other);
    $this->otherBooking = CreateBooking::run(
        Unit::create(['name' => 'B', 'slug' => 'b', 'base_price' => 100000]),
        ['check_in' => now()->addWeek()->toDateString(), 'check_out' => now()->addWeek()->addDay()->toDateString(), 'guests' => 1, 'guest_name' => 'T', 'guest_email' => 't@example.com'],
        BookingSource::VILLA_WEB,
    );

    $tenant = Tenant::create(['name' => 'Villa A', 'slug' => 'villa-a']);
    $owner = User::forceCreate(['name' => 'O', 'email' => 'o@example.com', 'password' => 'password123', 'tenant_id' => $tenant->id]);
    setPermissionsTeamId($tenant->id);
    $owner->assignRole('owner');
    setPermissionsTeamId(null);
    app()->forgetInstance(TenantContext::class);

    Sanctum::actingAs(User::find($owner->id));
});

it('does not reveal the model class or id when a record is missing', function () {
    $response = $this->getJson("/api/erp/bookings/{$this->otherBooking->id}")
        ->assertNotFound()
        ->assertExactJson(['message' => 'Not found.']);

    expect($response->getContent())->not->toContain('App\\\\Models')
        ->and($response->getContent())->not->toContain((string) $this->otherBooking->id);
});

it('keeps custom not found messages', function () {
    $this->postJson('/api/public/bookings/lookup', ['booking_code' => 'KS-ZZZZZZ', 'guest_email' => 'x@example.com'], ['X-Tenant' => 'villa-a'])
        ->assertNotFound()
        ->assertExactJson(['message' => 'Booking not found.']);
});
