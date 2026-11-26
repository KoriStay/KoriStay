<?php

use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

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

    $this->tenantlessUser = User::create([
        'name' => 'No Tenant',
        'email' => 'no-tenant@example.com',
        'password' => 'password123',
    ])->fresh();
});

it('rejects a tenantless user on erp routes even with a role in the requested tenant', function () {
    setPermissionsTeamId($this->tenantA->id);
    $this->tenantlessUser->assignRole('owner');
    setPermissionsTeamId(null);

    Sanctum::actingAs(User::find($this->tenantlessUser->id));

    $this->getJson('/api/erp/units', ['X-Tenant' => 'villa-a'])->assertForbidden();
});

it('rejects a tenantless user on erp routes without a role', function () {
    Sanctum::actingAs($this->tenantlessUser);

    $this->getJson('/api/erp/units', ['X-Tenant' => 'villa-a'])->assertForbidden();
});

it('still lets a tenantless user read public routes through the tenant header', function () {
    Sanctum::actingAs($this->tenantlessUser);

    $this->getJson('/api/public/units', ['X-Tenant' => 'villa-b'])
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'Unit B');
});

it('keeps a staff user on their own tenant when they send another tenant header', function () {
    $staff = User::create([
        'name' => 'Staff A',
        'email' => 'staff-a@example.com',
        'password' => 'password123',
        'tenant_id' => $this->tenantA->id,
    ]);
    setPermissionsTeamId($this->tenantA->id);
    $staff->assignRole('owner');
    setPermissionsTeamId(null);

    Sanctum::actingAs(User::find($staff->id));

    $this->getJson('/api/erp/units', ['X-Tenant' => 'villa-b'])
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'Unit A');
});
