<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Tenant and amount follow the booking.
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'tenant_id' => fn (array $attributes) => self::booking($attributes)->tenant_id,
            'gateway' => 'manual',
            'amount' => fn (array $attributes) => self::booking($attributes)->total_price,
            'status' => PaymentStatus::UNPAID,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function booking(array $attributes): Booking
    {
        return Booking::withoutGlobalScope(TenantScope::class)->findOrFail($attributes['booking_id']);
    }
}
