<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Http\Resources\BookingResource;
use Lorisleiva\Actions\Concerns\AsAction;

class CompleteBooking
{
    use AsAction;

    public function asController(int $booking): BookingResource
    {
        return BookingResource::make(ChangeBookingStatus::run($booking, BookingStatus::COMPLETED));
    }
}
