<?php

use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-12-01 03:00:00');
    $this->seed(RoleAndPermissionSeeder::class);

    $this->tenantA = Tenant::create(['name' => 'Villa A', 'slug' => 'villa-a']);
    $this->tenantB = Tenant::create(['name' => 'Villa B', 'slug' => 'villa-b']);

    app(TenantContext::class)->setTenant($this->tenantB);
    $this->unitB = Unit::create(['name' => 'Unit B', 'slug' => 'unit-b', 'base_price' => 900000]);

    app(TenantContext::class)->setTenant($this->tenantA);
    $this->unit = Unit::create(['name' => 'Unit A', 'slug' => 'unit-a', 'base_price' => 1500000, 'max_guests' => 4]);
    $this->hidden = Unit::create(['name' => 'Hidden', 'slug' => 'hidden', 'base_price' => 700000, 'is_published' => false]);

    app()->forgetInstance(TenantContext::class);
    setPermissionsTeamId(null);
});

function actAsStaffWithRole(Tenant $tenant, string $role): void
{
    $user = User::forceCreate([
        'name' => ucfirst($role),
        'email' => "{$role}@example.com",
        'password' => 'password123',
        'tenant_id' => $tenant->id,
    ]);
    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);
    setPermissionsTeamId(null);

    Sanctum::actingAs(User::find($user->id));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function postErpBooking(array $overrides = []): TestResponse
{
    return test()->postJson('/api/erp/bookings', [
        'unit_id' => test()->unit->id,
        'check_in' => '2026-12-10',
        'check_out' => '2026-12-12',
        'guests' => 2,
        'guest_name' => 'Walk In',
        'guest_email' => 'walkin@example.com',
        ...$overrides,
    ]);
}

it('lets staff with bookings.create record a confirmed erp_manual booking', function (string $role) {
    actAsStaffWithRole($this->tenantA, $role);

    $response = postErpBooking(['total_price' => 1, 'status' => 'pending', 'source' => 'villa_web'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.source', 'erp_manual')
        ->assertJsonPath('data.total_price', 3000000)
        ->assertJsonPath('data.expires_at', null);

    expect(array_keys($response->json('data')))->toBe([
        'booking_code', 'status', 'unit_id', 'check_in', 'check_out', 'guests',
        'guest_name', 'guest_email', 'guest_phone', 'total_price', 'expires_at',
        'id', 'source', 'created_at', 'updated_at',
    ]);
})->with(['owner', 'front_desk']);

it('forbids staff without bookings.create', function () {
    actAsStaffWithRole($this->tenantA, 'housekeeping');

    postErpBooking()->assertForbidden();
});

it('rejects guests', function () {
    postErpBooking()->assertUnauthorized();
});

it('allows staff to record a stay that already started', function () {
    actAsStaffWithRole($this->tenantA, 'front_desk');

    postErpBooking(['check_in' => '2026-11-29', 'check_out' => '2026-12-02'])
        ->assertCreated()
        ->assertJsonPath('data.check_in', '2026-11-29');
});

it('allows booking a unit that is not published', function () {
    actAsStaffWithRole($this->tenantA, 'front_desk');

    postErpBooking(['unit_id' => $this->hidden->id])
        ->assertCreated()
        ->assertJsonPath('data.total_price', 1400000);
});

it('rejects a unit of another tenant', function () {
    actAsStaffWithRole($this->tenantA, 'owner');

    postErpBooking(['unit_id' => $this->unitB->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('unit_id');
});

it('still validates dates and guests', function (array $overrides, string $field) {
    actAsStaffWithRole($this->tenantA, 'owner');

    postErpBooking($overrides)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'check out before check in' => [['check_out' => '2026-12-09'], 'check_out'],
    'too many guests' => [['guests' => 5], 'guests'],
    'missing email' => [['guest_email' => null], 'guest_email'],
]);

it('answers 409 when the dates are already taken', function () {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('The overlap constraint exists on Postgres only.');
    }

    actAsStaffWithRole($this->tenantA, 'owner');
    postErpBooking()->assertCreated();

    postErpBooking(['check_in' => '2026-12-11', 'check_out' => '2026-12-14'])
        ->assertStatus(409)
        ->assertJsonValidationErrors('check_in');
});
