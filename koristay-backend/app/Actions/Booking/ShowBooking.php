<?php

namespace App\Actions\Booking;

use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowBooking
{
    use AsAction;

    /**
     * Looked up here instead of route model binding, which runs before the tenant is resolved.
     */
    public function asController(int $booking): BookingResource
    {
        return BookingResource::make(Booking::findOrFail($booking));
    }
}
