<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        // Villa 1: Canggu
        $canggu = Tenant::firstOrCreate(
            ['slug' => 'kori-canggu'],
            [
                'name' => 'Villa Kori Canggu',
                'theme_config' => ['primary_color' => '#1E3A2F', 'template' => 'tropical-minimal'],
                'is_active' => true,
            ]
        );

        $ownerCanggu = User::firstOrCreate(
            ['email' => 'owner@koricanggu.test'],
            [
                'tenant_id' => $canggu->id,
                'name' => 'Made Canggu',
                'password' => Hash::make('password'),
            ]
        );
        setPermissionsTeamId($canggu->id);
        $ownerCanggu->assignRole('owner');

        Unit::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $canggu->id, 'slug' => 'ocean-pool-villa'],
            [
                'name' => 'Ocean Pool Villa',
                'description' => '2-bedroom private pool villa 5 minutes from Batu Bolong.',
                'base_price' => 2500000,
                'max_guests' => 4,
                'amenities' => ['private_pool', 'wifi', 'kitchen', 'ac'],
                'is_published' => true,
            ]
        );

        // Villa 2: Ubud
        $ubud = Tenant::firstOrCreate(
            ['slug' => 'ubud-jungle'],
            [
                'name' => 'Ubud Jungle Retreat',
                'theme_config' => ['primary_color' => '#3B2F2F', 'template' => 'wooden-sanctuary'],
                'is_active' => true,
            ]
        );

        $ownerUbud = User::firstOrCreate(
            ['email' => 'owner@ubudjungle.test'],
            [
                'tenant_id' => $ubud->id,
                'name' => 'Wayan Ubud',
                'password' => Hash::make('password'),
            ]
        );
        setPermissionsTeamId($ubud->id);
        $ownerUbud->assignRole('owner');

        Unit::withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $ubud->id, 'slug' => 'valley-bamboo-suite'],
            [
                'name' => 'Valley Bamboo Suite',
                'description' => 'Eco-luxury bamboo suite overlooking Ayung river valley.',
                'base_price' => 1800000,
                'max_guests' => 2,
                'amenities' => ['valley_view', 'breakfast', 'wifi', 'bathtub'],
                'is_published' => true,
            ]
        );
    }
}