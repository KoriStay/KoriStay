<?php

namespace App\Exceptions;

use App\Enums\BookingStatus;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The booking flow (pending -> confirmed -> checked_in -> completed, cancel from pending or
 * confirmed) does not allow this change from the booking's current status.
 */
class InvalidBookingTransitionException extends RuntimeException
{
    public function __construct(public readonly BookingStatus $from, public readonly BookingStatus $to)
    {
        parent::__construct("A {$from->value} booking cannot become {$to->value}.");
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'status' => $this->from->value], 409);
    }
}
