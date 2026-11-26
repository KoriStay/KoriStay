<?php

namespace App\Actions\Booking;

use App\Http\Resources\PublicBookingResource;
use App\Models\Booking;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class LookUpBooking
{
    use AsAction;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'booking_code' => ['required', 'string', 'max:20'],
            'guest_email' => ['required', 'email', 'max:255'],
        ];
    }

    /**
     * Both the code and the email must match, so a leaked code alone does not reveal the booking.
     */
    public function handle(string $bookingCode, string $guestEmail): ?Booking
    {
        return Booking::query()
            ->where('booking_code', Str::upper(trim($bookingCode)))
            ->whereRaw('lower(guest_email) = ?', [Str::lower(trim($guestEmail))])
            ->first();
    }

    public function asController(ActionRequest $request): PublicBookingResource
    {
        $booking = $this->handle($request->string('booking_code'), $request->string('guest_email'));

        abort_if($booking === null, 404, 'Booking not found.');

        return PublicBookingResource::make($booking);
    }
}
