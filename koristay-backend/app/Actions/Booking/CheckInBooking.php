<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Http\Resources\BookingResource;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckInBooking
{
    use AsAction;

    public function asController(int $booking): BookingResource
    {
        return BookingResource::make(ChangeBookingStatus::run($booking, BookingStatus::CHECKED_IN));
    }
}
