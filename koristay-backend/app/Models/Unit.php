<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concern\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use BelongsToTenant;

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
            'base_price'    => 'integer',
            'max_guests'    => 'integer',
            'amenities'     => 'array',
            'is_published'  => 'boolean',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
