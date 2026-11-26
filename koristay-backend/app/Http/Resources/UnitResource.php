<?php

namespace App\Http\Resources;

use App\Models\Unit;
use Illuminate\Http\Request;

/**
 * Unit as staff see it in the ERP: the public fields plus internal ones.
 *
 * @mixin Unit
 */
class UnitResource extends PublicUnitResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'is_published' => $this->is_published,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
