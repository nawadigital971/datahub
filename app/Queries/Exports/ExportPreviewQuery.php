<?php

namespace App\Queries\Exports;

use App\Actions\Exports\BuildExportRowAction;
use App\DTOs\AudienceFilters;
use App\Enums\ExportChannel;
use App\Queries\Audience\AudienceSummaryQuery;
use App\Queries\ExportRowsQuery;

/**
 * The first rows of an export as they will appear in the file, plus how many rows it will have.
 *
 * @phpstan-type Preview array{columns: list<string>, rows: list<list<string>>, total: int}
 */
class ExportPreviewQuery
{
    public const int ROWS = 40;

    public function __construct(
        private ExportRowsQuery $rows,
        private BuildExportRowAction $buildRow,
        private AudienceSummaryQuery $summary,
    ) {}

    /**
     * @return Preview
     */
    public function handle(AudienceFilters $filters, ExportChannel $channel, bool $hashed, bool $mask): array
    {
        $hashed = $hashed && $channel->supportsHashing();

        $rows = $this->rows->handle($filters, $channel)
            ->orderBy('id')
            ->limit(self::ROWS)
            ->get()
            ->map(fn (object $row): array => $this->buildRow->handle($row, $channel, $hashed, $mask))
            ->values()
            ->all();

        return [
            'columns' => $channel->columns(),
            'rows' => array_values($rows),
            'total' => $this->summary->handle($filters)['channels'][$channel->key()],
        ];
    }
}
