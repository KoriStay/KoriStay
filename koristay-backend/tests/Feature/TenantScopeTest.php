<?php

use App\Exceptions\TenantNotResolvedException;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenantA = Tenant::create(['name' => 'Villa A', 'slug' => 'villa-a']);
    $this->tenantB = Tenant::create(['name' => 'Villa B', 'slug' => 'villa-b']);

    app(TenantContext::class)->setTenant($this->tenantA);
    Unit::create(['name' => 'Unit A', 'slug' => 'unit-a', 'base_price' => 100000]);

    app(TenantContext::class)->setTenant($this->tenantB);
    Unit::create(['name' => 'Unit B', 'slug' => 'unit-b', 'base_price' => 200000]);

    $this->clearTenantContext = function () {
        app()->forgetInstance(TenantContext::class);
    };
});

it('only returns rows of the current tenant', function () {
    app(TenantContext::class)->setTenant($this->tenantB);

    expect(Unit::pluck('name')->all())->toBe(['Unit B']);
});

it('refuses to query tenant models when no tenant is resolved', function () {
    ($this->clearTenantContext)();

    Unit::query()->get();
})->throws(TenantNotResolvedException::class);

it('allows cross-tenant reads only when the scope is removed explicitly', function () {
    ($this->clearTenantContext)();

    expect(Unit::withoutGlobalScope(TenantScope::class)->count())->toBe(2);
});

it('rejects a tenant_id sent through mass assignment in strict mode', function () {
    app(TenantContext::class)->setTenant($this->tenantB);

    Unit::create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Sneaky',
        'slug' => 'sneaky',
        'base_price' => 1,
    ]);
})->throws(MassAssignmentException::class);

it('discards a tenant_id sent through mass assignment outside strict mode', function () {
    $strict = Model::preventsSilentlyDiscardingAttributes();
    Model::preventSilentlyDiscardingAttributes(false);

    try {
        app(TenantContext::class)->setTenant($this->tenantB);

        $unit = Unit::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Sneaky',
            'slug' => 'sneaky',
            'base_price' => 1,
        ]);

        expect($unit->tenant_id)->toBe($this->tenantB->id);
    } finally {
        Model::preventSilentlyDiscardingAttributes($strict);
    }
});

it('rejects public requests without a tenant header', function () {
    ($this->clearTenantContext)();

    $this->getJson('/api/public/units')->assertForbidden();
});

it('returns only the requested tenant on the public endpoint', function () {
    ($this->clearTenantContext)();

    $this->getJson('/api/public/units', ['X-Tenant' => 'villa-a'])
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Unit A');
});

it('rejects the erp endpoint for guests', function () {
    $this->getJson('/api/erp/units')->assertUnauthorized();
});
