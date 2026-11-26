<?php

use App\Models\Tenant;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores a unit description longer than 255 characters', function () {
    app(TenantContext::class)->setTenant(Tenant::create(['name' => 'Villa A', 'slug' => 'villa-a']));
    $description = str_repeat('Villa dengan kolam pribadi dan pemandangan sawah. ', 40);

    $unit = Unit::create([
        'name' => 'Unit A',
        'slug' => 'unit-a',
        'base_price' => 100000,
        'description' => $description,
    ]);

    expect(mb_strlen($description))->toBeGreaterThan(255)
        ->and($unit->fresh()->description)->toBe($description);
});
