<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * The unit already has a holding booking on the requested dates
 * (Postgres exclusion constraint bookings_unit_dates_no_overlap).
 */
class BookingDatesUnavailableException extends RuntimeException
{
    public function render(): JsonResponse
    {
        $message = 'The selected dates are no longer available.';

        return response()->json([
            'message' => $message,
            'errors' => ['check_in' => [$message]],
        ], 409);
    }
}
