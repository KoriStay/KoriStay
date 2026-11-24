<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\BookingSource;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concern\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'unit_id',
        'booking_code',
        'guest_name',
        'guest_email',
        'guest_phone',
        'check_in',
        'check_out',
        'total_price',
        'status',
        'source',
    ];

    protected function casts (): array
    {
        return [
            'check_in'  => 'date',
            'check_out' => 'date',
            'status'    => BookingStatus::class,
            'source'    => BookingSource::class,
        ];
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