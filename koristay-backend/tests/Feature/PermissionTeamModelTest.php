<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $this->tenantA = Tenant::create(['name' => 'Villa A', 'slug' => 'villa-a']);
    $this->tenantB = Tenant::create(['name' => 'Villa B', 'slug' => 'villa-b']);

    $this->user = User::create([
        'name' => 'Staff A',
        'email' => 'staff-a@example.com',
        'password' => 'password123',
        'tenant_id' => $this->tenantA->id,
    ]);

    setPermissionsTeamId($this->tenantA->id);
    $this->user->assignRole('owner');
    setPermissionsTeamId(null);
});

it('resolves the teams of a user as tenants', function () {
    expect($this->user->teams()->pluck('tenants.id')->all())->toBe([$this->tenantA->id]);
});

it('filters users by tenant with the team scope', function () {
    expect(User::team($this->tenantA)->pluck('users.id')->all())->toBe([$this->user->id])
        ->and(User::team($this->tenantB)->count())->toBe(0);
});
