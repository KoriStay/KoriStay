<?php

use App\Actions\Booking\CancelExpiredPendingBookings;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function bookingForExpiry(Unit $unit, ?BookingStatus $status = null): Booking
{
    $booking = new Booking([
        'unit_id' => $unit->id,
        'booking_code' => fake()->unique()->bothify('KS-######'),
        'guest_name' => 'Tamu',
        'guest_email' => 'tamu@example.com',
        'check_in' => '2026-12-01',
        'check_out' => '2026-12-03',
    ]);
    $booking->total_price = 200000;

    if ($status !== null) {
        $booking->status = $status;
    }

    $booking->save();

    return $booking;
}

function unpaidPaymentFor(Booking $booking): Payment
{
    $payment = new Payment(['booking_id' => $booking->id, 'gateway' => 'test']);
    $payment->amount = 200000;
    $payment->save();

    return $payment;
}

function paymentStatusOf(Payment $payment): PaymentStatus
{
    return Payment::withoutGlobalScope(TenantScope::class)->findOrFail($payment->id)->status;
}

function statusOf(Booking $booking): BookingStatus
{
    return Booking::withoutGlobalScope(TenantScope::class)->findOrFail($booking->id)->status;
}

beforeEach(function () {
    $this->freezeSecond();

    $this->tenantA = Tenant::create(['name' => 'Villa A', 'slug' => 'villa-a']);
    $this->tenantB = Tenant::create(['name' => 'Villa B', 'slug' => 'villa-b']);

    app(TenantContext::class)->setTenant($this->tenantB);
    $this->unitB = Unit::create(['name' => 'Unit B', 'slug' => 'unit-b', 'base_price' => 100000]);

    app(TenantContext::class)->setTenant($this->tenantA);
    $this->unitA = Unit::create(['name' => 'Unit A', 'slug' => 'unit-a', 'base_price' => 100000]);
});

it('holds a new pending booking for one hour', function () {
    $booking = bookingForExpiry($this->unitA);

    expect($booking->fresh()->expires_at->equalTo(now()->addHour()))->toBeTrue();
});

it('does not set an expiry on a booking created as confirmed', function () {
    $booking = bookingForExpiry($this->unitA, BookingStatus::CONFIRMED);

    expect($booking->fresh()->expires_at)->toBeNull();
});

it('cancels expired pending bookings of every tenant and leaves the rest', function () {
    $expiredA = bookingForExpiry($this->unitA);
    $confirmedA = bookingForExpiry($this->unitA, BookingStatus::CONFIRMED);
    app(TenantContext::class)->setTenant($this->tenantB);
    $expiredB = bookingForExpiry($this->unitB);

    $this->travel(61)->minutes();
    app(TenantContext::class)->setTenant($this->tenantA);
    $freshA = bookingForExpiry($this->unitA);
    app()->forgetInstance(TenantContext::class);

    expect(CancelExpiredPendingBookings::run())->toBe(2)
        ->and(statusOf($expiredA))->toBe(BookingStatus::CANCELLED)
        ->and(statusOf($expiredB))->toBe(BookingStatus::CANCELLED)
        ->and(statusOf($freshA))->toBe(BookingStatus::PENDING)
        ->and(statusOf($confirmedA))->toBe(BookingStatus::CONFIRMED);
});

it('expires the unpaid payments of bookings it cancels and leaves payments of held bookings', function () {
    $expired = bookingForExpiry($this->unitA);
    $expiredPayment = unpaidPaymentFor($expired);
    app(TenantContext::class)->setTenant($this->tenantB);
    $expiredB = bookingForExpiry($this->unitB);
    $expiredPaymentB = unpaidPaymentFor($expiredB);

    $this->travel(61)->minutes();
    app(TenantContext::class)->setTenant($this->tenantA);
    $held = bookingForExpiry($this->unitA);
    $heldPayment = unpaidPaymentFor($held);
    app()->forgetInstance(TenantContext::class);

    CancelExpiredPendingBookings::run();

    expect(paymentStatusOf($expiredPayment))->toBe(PaymentStatus::EXPIRED)
        ->and(paymentStatusOf($expiredPaymentB))->toBe(PaymentStatus::EXPIRED)
        ->and(paymentStatusOf($heldPayment))->toBe(PaymentStatus::UNPAID);
});

it('keeps a pending booking until its hold has fully passed', function () {
    $booking = bookingForExpiry($this->unitA);
    app()->forgetInstance(TenantContext::class);

    $this->travel(59)->minutes();

    expect(CancelExpiredPendingBookings::run())->toBe(0)
        ->and(statusOf($booking))->toBe(BookingStatus::PENDING);
});

it('cancels expired bookings through the artisan command', function () {
    $booking = bookingForExpiry($this->unitA);
    app()->forgetInstance(TenantContext::class);
    $this->travel(61)->minutes();

    $this->artisan('bookings:cancel-expired')->assertSuccessful();

    expect(statusOf($booking))->toBe(BookingStatus::CANCELLED);
});

it('schedules the expiry command every minute without overlapping', function () {
    app(Kernel::class)->bootstrap();

    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'bookings:cancel-expired'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
