<?php

namespace App\Actions\Booking;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Exceptions\BookingDatesUnavailableException;
use App\Http\Resources\PublicBookingResource;
use App\Models\Booking;
use App\Models\Unit;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateBooking
{
    use AsAction;

    /**
     * Rules for guests booking on a villa website (source villa_web).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'unit_id' => [
                'required',
                'integer',
                Rule::exists('units', 'id')
                    ->where('tenant_id', app(TenantContext::class)->id())
                    ->where('is_published', true),
            ],
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now(Booking::LOCAL_TIMEZONE)->toDateString()],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'guests' => ['required', 'integer', 'min:1'],
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_email' => ['required', 'email', 'max:255'],
            'guest_phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * Prices the stay on the server and frees the unit from expired pending holds first.
     * erp_manual bookings start confirmed, every other source starts pending.
     *
     * @param  array{check_in: string, check_out: string, guests: int, guest_name: string, guest_email: string, guest_phone?: ?string}  $details
     *
     * @throws BookingDatesUnavailableException
     */
    public function handle(Unit $unit, array $details, BookingSource $source): Booking
    {
        return DB::transaction(function () use ($unit, $details, $source): Booking {
            CancelExpiredPendingBookings::run($unit);

            $nights = (int) CarbonImmutable::parse($details['check_in'])->diffInDays($details['check_out']);

            $booking = new Booking([...$details, 'unit_id' => $unit->id]);
            $booking->total_price = $unit->base_price * $nights;
            $booking->source = $source;
            $booking->status = $source === BookingSource::ERP_MANUAL ? BookingStatus::CONFIRMED : BookingStatus::PENDING;

            try {
                $booking->save();
            } catch (QueryException $exception) {
                if ($exception->getCode() === '23P01') {
                    throw new BookingDatesUnavailableException(previous: $exception);
                }

                throw $exception;
            }

            return $booking;
        });
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $booking = $this->handle(
            Unit::findOrFail($validated['unit_id']),
            Arr::except($validated, 'unit_id'),
            BookingSource::VILLA_WEB,
        );

        return PublicBookingResource::make($booking)->response()->setStatusCode(201);
    }
}
