<?php

namespace App\Actions\Unit;

use App\Http\Resources\PublicUnitResource;
use App\Models\Unit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ListPublishedUnits
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
        return Unit::query()->where('is_published', true)->orderBy('id')->paginate($perPage);
    }

    public function asController(ActionRequest $request): AnonymousResourceCollection
    {
        return PublicUnitResource::collection($this->handle($request->integer('per_page', 20)));
    }
}
