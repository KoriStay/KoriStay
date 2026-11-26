<?php

namespace Database\Factories;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Scopes\TenantScope;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Tenant and price follow the unit. Every booking gets its own week, so bookings
 * on one unit never hit the Postgres overlap constraint.
 *
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected static int $week = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkIn = CarbonImmutable::parse('2030-01-07')->addWeeks(static::$week++);

        return [
            'unit_id' => Unit::factory(),
            'tenant_id' => fn (array $attributes) => self::unit($attributes)->tenant_id,
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkIn->addDays(2)->toDateString(),
            'guests' => 1,
            'guest_name' => fake()->name(),
            'guest_email' => fake()->safeEmail(),
            'total_price' => fn (array $attributes) => self::unit($attributes)->base_price
                * (int) CarbonImmutable::parse($attributes['check_in'])->diffInDays($attributes['check_out']),
            'status' => BookingStatus::PENDING,
            'source' => BookingSource::VILLA_WEB,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['status' => BookingStatus::CONFIRMED]);
    }

    public function checkedIn(): static
    {
        return $this->state(['status' => BookingStatus::CHECKED_IN]);
    }

    public function completed(): static
    {
        return $this->state(['status' => BookingStatus::COMPLETED]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => BookingStatus::CANCELLED]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function unit(array $attributes): Unit
    {
        return Unit::withoutGlobalScope(TenantScope::class)->findOrFail($attributes['unit_id']);
    }
}
