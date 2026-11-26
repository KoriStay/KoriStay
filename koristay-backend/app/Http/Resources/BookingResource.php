<?php

namespace App\Http\Resources;

use App\Models\Booking;
use Illuminate\Http\Request;

/**
 * Booking as staff see it in the ERP: the guest view plus internal fields.
 *
 * @mixin Booking
 */
class BookingResource extends PublicBookingResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'id' => $this->id,
            'source' => $this->source->value,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
