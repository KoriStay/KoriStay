<?php

namespace App\Models;

use App\Models\Concern\BelongsToTenant;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'base_price',
        'max_guests',
        'amenities',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'max_guests' => 'integer',
            'amenities' => 'array',
            'is_published' => 'boolean',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
