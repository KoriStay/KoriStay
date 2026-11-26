<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function staffOf(Tenant $tenant, string $email): User
{
    $user = User::forceCreate([
        'name' => 'Staff',
        'email' => $email,
        'password' => 'password123',
        'tenant_id' => $tenant->id,
    ]);
    setPermissionsTeamId($tenant->id);
    $user->assignRole('owner');
    setPermissionsTeamId(null);

    return User::find($user->id);
}

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->tenant = Tenant::create(['name' => 'Villa A', 'slug' => 'villa-a']);
    app()->forgetInstance(TenantContext::class);
});

it('allows 60 api requests per minute from one guest and blocks the next', function () {
    foreach (range(1, 60) as $request) {
        $this->getJson('/api/public/units', ['X-Tenant' => 'villa-a'])->assertOk();
    }

    $this->getJson('/api/public/units', ['X-Tenant' => 'villa-a'])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
});

it('sends the rate limit headers', function () {
    $this->getJson('/api/public/units', ['X-Tenant' => 'villa-a'])
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', '60')
        ->assertHeader('X-RateLimit-Remaining', '59');
});

it('counts logged in staff per user, not per address', function () {
    $first = staffOf($this->tenant, 'first@example.com');
    $second = staffOf($this->tenant, 'second@example.com');

    Sanctum::actingAs($first);
    foreach (range(1, 60) as $request) {
        $this->getJson('/api/erp/units')->assertOk();
    }
    $this->getJson('/api/erp/units')->assertTooManyRequests();

    Sanctum::actingAs($second);
    $this->getJson('/api/erp/units')->assertOk();
});

it('allows requests again after a minute', function () {
    foreach (range(1, 60) as $request) {
        $this->getJson('/api/public/units', ['X-Tenant' => 'villa-a']);
    }

    $this->travel(61)->seconds();

    $this->getJson('/api/public/units', ['X-Tenant' => 'villa-a'])->assertOk();
});
