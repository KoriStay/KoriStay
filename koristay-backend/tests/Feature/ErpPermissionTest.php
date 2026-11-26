<?php

use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function makeStaffUser(Tenant $tenant, ?string $role = null, ?Tenant $roleTenant = null): User
{
    $user = User::create([
        'name' => 'Staff',
        'email' => fake()->unique()->safeEmail(),
        'password' => 'password123',
        'tenant_id' => $tenant->id,
    ]);

    if ($role !== null) {
        setPermissionsTeamId(($roleTenant ?? $tenant)->id);
        $user->assignRole($role);
    }

    return User::find($user->id);
}

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->tenantA = Tenant::create(['name' => 'Villa A', 'slug' => 'villa-a']);
    $this->tenantB = Tenant::create(['name' => 'Villa B', 'slug' => 'villa-b']);

    app(TenantContext::class)->setTenant($this->tenantA);
    Unit::create(['name' => 'Unit A', 'slug' => 'unit-a', 'base_price' => 100000]);

    app(TenantContext::class)->setTenant($this->tenantB);
    Unit::create(['name' => 'Unit B', 'slug' => 'unit-b', 'base_price' => 200000]);

    app()->forgetInstance(TenantContext::class);
    setPermissionsTeamId(null);
});

it('rejects guests on the erp units endpoint', function () {
    $this->getJson('/api/erp/units')->assertUnauthorized();
});

it('rejects a staff user without any role', function () {
    Sanctum::actingAs(makeStaffUser($this->tenantA));

    $this->getJson('/api/erp/units')->assertForbidden();
});

it('lets every staff role with units.view list only its own tenant units', function (string $role) {
    Sanctum::actingAs(makeStaffUser($this->tenantA, $role));

    $this->getJson('/api/erp/units')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Unit A');
})->with(['owner', 'front_desk', 'housekeeping']);

it('does not honour a role that was granted inside another tenant', function () {
    Sanctum::actingAs(makeStaffUser($this->tenantA, 'owner', roleTenant: $this->tenantB));

    $this->getJson('/api/erp/units')->assertForbidden();
});
