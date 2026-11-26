<?php

namespace App\Models;

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Models\Concern\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'check_in',
        'check_out',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
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
        });
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
