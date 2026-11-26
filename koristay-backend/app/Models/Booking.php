<?php

namespace App\Models;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Concern\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Booking extends Model
{
    use BelongsToTenant;

    /**
     * How long a pending booking holds its unit before it is cancelled.
     */
    public const PENDING_HOLD_MINUTES = 60;

    protected $fillable = [
        'unit_id',
        'booking_code',
        'guest_name',
        'guest_email',
        'guest_phone',
        'guests',
        'check_in',
        'check_out',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'guests' => 'integer',
            'status' => BookingStatus::class,
            'source' => BookingSource::class,
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            $isPending = ($booking->status ?? BookingStatus::PENDING) === BookingStatus::PENDING;

            if ($isPending && $booking->expires_at === null) {
                $booking->expires_at = now()->addMinutes(self::PENDING_HOLD_MINUTES);
            }

            $booking->ensureGuestsFitUnit();
        });

        static::updating(function (Booking $booking) {
            if ($booking->isDirty(['guests', 'unit_id'])) {
                $booking->ensureGuestsFitUnit();
            }
        });
    }

    /**
     * Guests must be between 1 and the unit's max_guests. The unit is read by
     * tenant_id explicitly so this also works without a tenant context.
     *
     * @throws ValidationException
     */
    protected function ensureGuestsFitUnit(): void
    {
        $maxGuests = Unit::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $this->tenant_id)
            ->whereKey($this->unit_id)
            ->value('max_guests');

        if ($this->guests !== null && $this->guests >= 1 && ($maxGuests === null || $this->guests <= $maxGuests)) {
            return;
        }

        throw ValidationException::withMessages([
            'guests' => [$maxGuests === null
                ? 'The number of guests must be at least 1.'
                : "The number of guests must be between 1 and {$maxGuests}."],
        ]);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
