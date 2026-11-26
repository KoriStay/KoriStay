<?php

use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['slug' => 'villa-a', 'custom_domain' => 'villa-a.co.id']);
    $this->unit = Unit::factory()->for($this->tenant)->create(['name' => 'Unit A']);
});

function publicUnits(array $headers): TestResponse
{
    return test()->getJson('/api/public/units', $headers);
}

it('resolves the tenant from the slug or the custom domain in X-Tenant', function (string $identifier) {
    publicUnits(['X-Tenant' => $identifier])
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Unit A');
})->with(['villa-a', 'villa-a.co.id']);

it('rejects an unknown tenant', function () {
    publicUnits(['X-Tenant' => 'nobody'])->assertForbidden();
});

it('rejects an inactive tenant by slug and by custom domain', function (string $identifier) {
    $this->tenant->update(['is_active' => false]);

    publicUnits(['X-Tenant' => $identifier])->assertForbidden();
})->with(['villa-a', 'villa-a.co.id']);

it('rejects staff whose tenant was deactivated after they logged in', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $staff = User::factory()->create();
    $staff->forceFill(['tenant_id' => $this->tenant->id])->save();
    setPermissionsTeamId($this->tenant->id);
    $staff->assignRole('owner');
    setPermissionsTeamId(null);

    $this->tenant->update(['is_active' => false]);
    Sanctum::actingAs($staff->fresh());

    $this->getJson('/api/erp/units')
        ->assertForbidden()
        ->assertJsonPath('message', 'Your organization is not active or unavailable.');
});

it('resolves each custom domain to its own tenant', function () {
    Tenant::factory()->create(['slug' => 'villa-b', 'custom_domain' => 'villa-b.co.id']);

    publicUnits(['X-Tenant' => 'villa-b.co.id'])->assertOk()->assertJsonCount(0, 'data');
});
