<?php

use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function makeUnits(int $count, bool $published = true, string $prefix = 'unit'): void
{
    foreach (range(1, $count) as $number) {
        Unit::create([
            'name' => ucfirst($prefix)." {$number}",
            'slug' => "{$prefix}-{$number}",
            'base_price' => 100000,
            'is_published' => $published,
        ]);
    }
}

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->tenant = Tenant::create(['name' => 'Villa A', 'slug' => 'villa-a']);
    $this->staff = User::forceCreate([
        'name' => 'Owner',
        'email' => 'owner@example.com',
        'password' => 'password123',
        'tenant_id' => $this->tenant->id,
    ]);
    setPermissionsTeamId($this->tenant->id);
    $this->staff->assignRole('owner');

    app(TenantContext::class)->setTenant($this->tenant);
});

function asGuest(): void
{
    app()->forgetInstance(TenantContext::class);
    setPermissionsTeamId(null);
}

function asStaff(User $staff): void
{
    asGuest();
    Sanctum::actingAs(User::find($staff->id));
}

it('lists published units for guests with only the public fields', function () {
    makeUnits(2);
    makeUnits(1, published: false, prefix: 'hidden');
    asGuest();

    $response = $this->getJson('/api/public/units', ['X-Tenant' => 'villa-a'])->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and(array_keys($response->json('data.0')))->toBe([
            'id', 'name', 'slug', 'description', 'base_price', 'max_guests', 'amenities',
        ])
        ->and($response->json('meta.total'))->toBe(2);
});

it('lists every unit for staff with the erp fields and without tenant_id', function () {
    makeUnits(1);
    makeUnits(1, published: false, prefix: 'hidden');
    asStaff($this->staff);

    $response = $this->getJson('/api/erp/units')->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and(array_keys($response->json('data.0')))->toBe([
            'id', 'name', 'slug', 'description', 'base_price', 'max_guests', 'amenities',
            'is_published', 'created_at', 'updated_at',
        ])
        ->and(collect($response->json('data'))->pluck('is_published')->all())->toBe([true, false]);
});

it('paginates 20 units per page by default', function (string $path, array $headers) {
    makeUnits(25);
    str_starts_with($path, '/api/erp') ? asStaff($this->staff) : asGuest();

    $this->getJson($path, $headers)
        ->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonPath('meta.total', 25)
        ->assertJsonPath('meta.last_page', 2);

    $this->getJson("{$path}?page=2", $headers)->assertJsonCount(5, 'data');
})->with([
    'public' => ['/api/public/units', ['X-Tenant' => 'villa-a']],
    'erp' => ['/api/erp/units', []],
]);

it('lets the client choose up to 100 units per page', function () {
    makeUnits(7);
    asGuest();

    $this->getJson('/api/public/units?per_page=5', ['X-Tenant' => 'villa-a'])
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.per_page', 5);
});

it('rejects a page size outside 1 to 100', function (int $perPage) {
    asGuest();

    $this->getJson("/api/public/units?per_page={$perPage}", ['X-Tenant' => 'villa-a'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
})->with([0, 101]);
