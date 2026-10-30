<?php

namespace App\Queries\ReferenceData;

use App\Queries\ReferenceData\Types\ReferenceType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ReferenceItemListQuery
{
    public const int PER_PAGE = 50;

    /**
     * @param  ReferenceType<Model>  $type
     * @return LengthAwarePaginator<int, array{id: int, title: string, values: array<string, mixed>}>
     */
    public function handle(ReferenceType $type, Request $request, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $paginator = QueryBuilder::for($type->query(), $request)
            ->allowedFilters(AllowedFilter::callback('search', function (Builder $query, mixed $value) use ($type): void {
                $term = is_array($value) ? implode(' ', array_map(strval(...), $value)) : (is_scalar($value) ? trim((string) $value) : '');
                if ($term !== '') {
                    $type->search($query, $term);
                }
            }))
            ->allowedSorts(...$type->sorts())
            ->defaultSort($type->defaultSort(), 'id')
            ->allowedIncludes()
            ->paginate($perPage)
            ->withQueryString();

        return $paginator->through(fn (Model $item): array => $type->present($item));
    }
}
