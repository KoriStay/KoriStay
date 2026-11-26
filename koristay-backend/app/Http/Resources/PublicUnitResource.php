<?php

namespace App\Http\Resources;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Unit as guests see it on a villa website.
 *
 * @mixin Unit
 */
class PublicUnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'base_price' => $this->base_price,
            'max_guests' => $this->max_guests,
            'amenities' => $this->amenities,
        ];
    }
}
