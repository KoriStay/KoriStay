<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * @return array<string, int>
 */
function seededCounts(): array
{
    return [
        'permissions' => Permission::count(),
        'roles' => Role::count(),
        'tenants' => Tenant::count(),
        'users' => User::count(),
    ];
}

it('seeds the demo data', function () {
    $this->seed();

    expect(seededCounts())->toBe(['permissions' => 14, 'roles' => 3, 'tenants' => 2, 'users' => 3]);
});

it('can run the seeders again without errors or duplicates', function () {
    $this->seed();
    $this->seed();

    expect(seededCounts())->toBe(['permissions' => 14, 'roles' => 3, 'tenants' => 2, 'users' => 3]);
});
