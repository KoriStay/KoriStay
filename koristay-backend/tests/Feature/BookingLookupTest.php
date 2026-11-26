<?php

use App\Actions\Booking\CreateBooking;
use App\Enums\BookingSource;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-12-01 03:00:00');

    Tenant::create(['name' => 'Villa B', 'slug' => 'villa-b']);
    $this->tenantA = Tenant::create(['name' => 'Villa A', 'slug' => 'villa-a']);
    app(TenantContext::class)->setTenant($this->tenantA);

    $this->booking = CreateBooking::run(
        Unit::create(['name' => 'Unit A', 'slug' => 'unit-a', 'base_price' => 100000]),
        [
            'check_in' => '2026-12-10',
            'check_out' => '2026-12-12',
            'guests' => 2,
            'guest_name' => 'Ayu Lestari',
            'guest_email' => 'Ayu@Example.com',
        ],
        BookingSource::VILLA_WEB,
    );

    app()->forgetInstance(TenantContext::class);
});

function lookup(string $code, string $email, string $tenant = 'villa-a'): TestResponse
{
    return test()->postJson('/api/public/bookings/lookup', ['booking_code' => $code, 'guest_email' => $email], ['X-Tenant' => $tenant]);
}

it('shows the booking when the code and email match', function () {
    lookup($this->booking->booking_code, 'Ayu@Example.com')
        ->assertOk()
        ->assertJsonPath('data.booking_code', $this->booking->booking_code)
        ->assertJsonPath('data.guest_name', 'Ayu Lestari')
        ->assertJsonMissingPath('data.id')
        ->assertJsonMissingPath('data.source');
});

it('ignores case and surrounding spaces in the code and email', function () {
    lookup('  '.strtolower($this->booking->booking_code).' ', 'ayu@example.COM')->assertOk();
});

it('answers 404 without saying which part is wrong', function (string $code, string $email) {
    lookup($code === 'right' ? $this->booking->booking_code : 'KS-ZZZZZZ', $email)
        ->assertNotFound()
        ->assertJsonPath('message', 'Booking not found.');
})->with([
    'wrong email' => ['right', 'other@example.com'],
    'wrong code' => ['wrong', 'ayu@example.com'],
]);

it('does not find a booking through another villa', function () {
    lookup($this->booking->booking_code, 'ayu@example.com', 'villa-b')->assertNotFound();
});

it('requires the code and a valid email', function () {
    $this->postJson('/api/public/bookings/lookup', ['guest_email' => 'not-an-email'], ['X-Tenant' => 'villa-a'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['booking_code', 'guest_email']);
});

it('allows 10 lookups per minute per address', function () {
    foreach (range(1, 10) as $attempt) {
        lookup('KS-ZZZZZZ', 'ayu@example.com')->assertNotFound();
    }

    lookup($this->booking->booking_code, 'ayu@example.com')->assertTooManyRequests();
});
