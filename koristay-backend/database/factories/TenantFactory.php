<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Villa '.fake()->unique()->lastName(),
            'slug' => fake()->unique()->slug(2),
            'is_active' => true,
        ];
    }
}
