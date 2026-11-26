<?php

namespace App\Actions\Booking;

use App\Enums\BookingSource;
use App\Http\Resources\BookingResource;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateErpBooking
{
    use AsAction;

    /**
     * Staff may record stays that already started and book unpublished units.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('tenant_id', app(TenantContext::class)->id())],
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in'],
            'guests' => ['required', 'integer', 'min:1'],
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_email' => ['required', 'email', 'max:255'],
            'guest_phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $booking = CreateBooking::run(
            Unit::findOrFail($validated['unit_id']),
            Arr::except($validated, 'unit_id'),
            BookingSource::ERP_MANUAL,
        );

        return BookingResource::make($booking)->response()->setStatusCode(201);
    }
}
