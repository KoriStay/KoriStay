<?php

namespace App\Actions\Unit;

use App\Http\Resources\UnitResource;
use App\Models\Unit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ListUnits
{
    use AsAction;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function handle(int $perPage = 20): LengthAwarePaginator
    {
        return Unit::query()->orderBy('id')->paginate($perPage);
    }

    public function asController(ActionRequest $request): AnonymousResourceCollection
    {
        return UnitResource::collection($this->handle($request->integer('per_page', 20)));
    }
}
