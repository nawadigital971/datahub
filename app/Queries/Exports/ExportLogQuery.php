<?php

namespace App\Queries\Exports;

use App\Enums\ExportChannel;
use App\Models\Export;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ExportLogQuery
{
    public const int PER_PAGE = 25;

    /**
     * @return LengthAwarePaginator<int, Export>
     */
    public function handle(Request $request): LengthAwarePaginator
    {
        return QueryBuilder::for(Export::query()->with(['user:id,name', 'media']), $request)
            ->allowedFilters(
                AllowedFilter::callback('channel', fn (Builder $query, mixed $value) => $query->where('channel', ExportChannel::fromKey((string) $value))),
                AllowedFilter::exact('user', 'user_id'),
            )
            ->allowedSorts('created_at', 'row_count')
            ->defaultSort('-created_at')
            ->allowedIncludes()
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * People who have exported something (for the "who" filter).
     *
     * @return list<array{value: int, label: string}>
     */
    public function exporters(): array
    {
        return array_values(User::query()
            ->whereIn('id', Export::query()->select('user_id'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => ['value' => $user->id, 'label' => $user->name])
            ->all());
    }
}
