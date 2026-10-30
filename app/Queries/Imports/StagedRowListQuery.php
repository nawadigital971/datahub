<?php

namespace App\Queries\Imports;

use App\Models\ImportBatch;
use App\Models\StagedRow;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The preview table: rows of one batch in file order, filtered, cursor-paginated.
 */
class StagedRowListQuery
{
    public const int PER_PAGE = 50;

    public function __construct(private StagedRowFilters $filters) {}

    /**
     * @return CursorPaginator<int, StagedRow>
     */
    public function handle(ImportBatch $batch, Request $request): CursorPaginator
    {
        $filter = fn (string $key): AllowedFilter => AllowedFilter::callback(
            $key,
            fn (Builder $query, mixed $value) => $this->filters->applyKey($query, $key, $this->filters->values($value)),
        );

        return QueryBuilder::for(StagedRow::query()->with('dupOf:id,row_number')->where('import_batch_id', $batch->id), $request)
            ->allowedFilters(...array_map($filter, StagedRowFilters::KEYS))
            ->allowedSorts('row_number')
            ->defaultSort('row_number')
            ->allowedIncludes()
            ->cursorPaginate(self::PER_PAGE)
            ->withQueryString();
    }

    /**
     * How many rows in the batch carry each issue (for the issue filter).
     *
     * @return array<string, int>
     */
    public function issueCounts(ImportBatch $batch): array
    {
        return StagedRow::query()
            ->where('import_batch_id', $batch->id)
            ->selectRaw('unnest(issue_codes) AS code, count(*) AS total')
            ->groupBy('code')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->code => (int) $row->total])
            ->all();
    }
}
