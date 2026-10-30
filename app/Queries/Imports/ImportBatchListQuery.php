<?php

namespace App\Queries\Imports;

use App\Models\ImportBatch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ImportBatchListQuery
{
    public const int PER_PAGE = 20;

    /**
     * @return LengthAwarePaginator<int, ImportBatch>
     */
    public function handle(Request $request): LengthAwarePaginator
    {
        return QueryBuilder::for(ImportBatch::query()->with(['source', 'user:id,name']), $request)
            ->allowedFilters(AllowedFilter::exact('source', 'source_id'), AllowedFilter::exact('status'))
            ->allowedSorts('created_at')
            ->defaultSort('-created_at')
            ->allowedIncludes()
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }
}
