<?php

namespace App\Actions\Booking;

use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ListBookings
{
    use AsAction;

    /**
     * Date filters are validated here because an invalid date breaks the Postgres query.
     * They use whereDate() because SQLite stores date columns as "Y-m-d H:i:s" text.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'filter.check_in_from' => ['nullable', 'date_format:Y-m-d'],
            'filter.check_in_to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * Filters: status (comma separated), unit_id, check_in_from, check_in_to.
     * Sorts: check_in, created_at (prefix "-" for descending). Default: latest check in first.
     */
    public function handle(int $perPage = 20): LengthAwarePaginator
    {
        return QueryBuilder::for(Booking::class)
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('unit_id'),
                AllowedFilter::callback('check_in_from', fn (Builder $query, string $date) => $query->whereDate('check_in', '>=', $date)),
                AllowedFilter::callback('check_in_to', fn (Builder $query, string $date) => $query->whereDate('check_in', '<=', $date)),
            )
            ->allowedSorts('check_in', 'created_at')
            ->defaultSort('-check_in')
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function asController(ActionRequest $request): AnonymousResourceCollection
    {
        return BookingResource::collection($this->handle($request->integer('per_page', 20)));
    }
}
