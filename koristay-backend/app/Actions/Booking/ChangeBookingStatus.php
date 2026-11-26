<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Exceptions\InvalidBookingTransitionException;
use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ChangeBookingStatus
{
    use AsAction;

    /**
     * Locks the booking, checks the move against the booking flow and saves it.
     * Leaving pending clears expires_at so the expiry job leaves the booking alone.
     *
     * @throws InvalidBookingTransitionException
     */
    public function handle(int $bookingId, BookingStatus $to): Booking
    {
        return DB::transaction(function () use ($bookingId, $to): Booking {
            $booking = Booking::query()->lockForUpdate()->findOrFail($bookingId);

            if (! in_array($to, self::allowedFrom($booking->status), true)) {
                throw new InvalidBookingTransitionException($booking->status, $to);
            }

            $booking->status = $to;
            $booking->expires_at = null;
            $booking->save();

            return $booking;
        });
    }

    /**
     * @return array<int, BookingStatus>
     */
    public static function allowedFrom(BookingStatus $status): array
    {
        return match ($status) {
            BookingStatus::PENDING => [BookingStatus::CONFIRMED, BookingStatus::CANCELLED],
            BookingStatus::CONFIRMED => [BookingStatus::CHECKED_IN, BookingStatus::CANCELLED],
            BookingStatus::CHECKED_IN => [BookingStatus::COMPLETED],
            default => [],
        };
    }
}
