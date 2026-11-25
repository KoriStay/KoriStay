<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        $permissions = [
            // Units (Villas / Rooms)
            'units.view',
            'units.create',
            'units.update',
            'units.delete',

            // Bookings
            'bookings.view',
            'bookings.create',
            'bookings.update',
            'bookings.cancel',
            'bookings.check-in',

            // Payments
            'payments.view',
            'payments.record',
            'payments.refund',
            
            // Tenants // Villa Settings & Staff
            'tenants.manage-settings',
            'tenants.manage-staff',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => $guard]);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $owner = Role::firstOrCreate(
            [
                'name' => 'owner', 
                'guard_name' => $guard, 
                'tenant_id' => null
            ]
        );

        $owner->syncPermissions($permissions);

        $frontDesk = Role::firstOrCreate(
            [
                'name' => 'front_desk', 
                'guard_name' => $guard, 
                'tenant_id' => null
            ]
        );

        $frontDesk->syncPermissions([
            'units.view',
            'bookings.view',
            'bookings.create',
            'bookings.update',
            'bookings.cancel',
            'bookings.check-in',
            'payments.view',
            'payments.record',
        ]);

        $housekeeping = Role::firstOrCreate(
            [
                'name' => 'housekeeping', 
                'guard_name' => $guard, 
                'tenant_id' => null
            ]
        );

        $housekeeping->syncPermissions([
            'units.view',
            'bookings.view',
        ]);
    }
}
