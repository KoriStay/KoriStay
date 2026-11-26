<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a unit that belongs to a new tenant without a tenant context', function () {
    $unit = Unit::factory()->create();

    expect($unit->tenant_id)->toBe(Tenant::sole()->id)
        ->and($unit->is_published)->toBeTrue();
});

it('creates a pending booking whose tenant and price follow its unit', function () {
    $unit = Unit::factory()->create(['base_price' => 500000]);

    $booking = Booking::factory()->for($unit)->create(['check_in' => '2027-05-01', 'check_out' => '2027-05-04']);

    expect($booking->tenant_id)->toBe($unit->tenant_id)
        ->and($booking->status)->toBe(BookingStatus::PENDING)
        ->and($booking->total_price)->toBe(1500000)
        ->and($booking->booking_code)->toStartWith('KS-')
        ->and($booking->expires_at)->not->toBeNull();
});

it('offers booking states for every status', function (string $state, BookingStatus $status) {
    expect(Booking::factory()->{$state}()->create()->status)->toBe($status);
})->with([
    ['confirmed', BookingStatus::CONFIRMED],
    ['checkedIn', BookingStatus::CHECKED_IN],
    ['completed', BookingStatus::COMPLETED],
    ['cancelled', BookingStatus::CANCELLED],
]);

it('creates several bookings on one unit without overlapping dates', function () {
    $unit = Unit::factory()->create();

    $bookings = Booking::factory()->count(3)->for($unit)->create();

    expect($bookings->pluck('check_in')->map->toDateString()->unique())->toHaveCount(3);
});

it('creates an unpaid payment for the booking amount in the booking tenant', function () {
    $booking = Booking::factory()->create();

    $payment = Payment::factory()->for($booking)->create();

    expect($payment->tenant_id)->toBe($booking->tenant_id)
        ->and($payment->amount)->toBe($booking->total_price)
        ->and($payment->status)->toBe(PaymentStatus::UNPAID);
});
