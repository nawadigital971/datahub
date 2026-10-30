<?php

namespace App\Actions\Imports;

use App\Actions\Database\CopyRowsAction;
use App\DTOs\Imports\AnalysisContext;
use App\DTOs\Imports\AnalyzedRow;
use App\DTOs\Imports\MappedRow;
use App\DTOs\Imports\StageContactsData;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportOrigin;
use App\Models\ImportBatch;
use App\Queries\Sources\SourceNameMatcher;
use App\Settings\DataSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stages rows sent by Claude over MCP into a review batch (a new one, or one of the caller's own MCP batches).
 * The rows go through the same analysis as uploaded files and wait for a person to review and commit them
 * in the dashboard. MCP can never commit.
 */
class StageContactsAction
{
    public const int MAX_ROWS_PER_CALL = 5_000;

    public function __construct(
        private AnalyzeRowAction $analyze,
        private CopyRowsAction $copy,
        private DetectDuplicatesAction $duplicates,
        private RecountBatchAction $recount,
        private DataSettings $settings,
        private SourceNameMatcher $sources,
    ) {}

    public function handle(StageContactsData $data): ImportBatch
    {
        $batch = $data->batchId === null ? $this->newBatch($data) : $this->existingBatch($data);
        $context = new AnalysisContext($batch->source?->collected_year, $this->settings->stale_data_year_threshold);

        DB::transaction(function () use ($batch, $data, $context): void {
            $nextRow = (int) DB::table('staged_rows')->where('import_batch_id', $batch->id)->max('row_number') + 1;

            $lines = [];
            foreach ($data->rows as $offset => $row) {
                $raw = array_filter(
                    array_map(fn (mixed $value): string => is_array($value) ? implode(', ', array_map(strval(...), array_filter($value, is_scalar(...)))) : (is_scalar($value) ? (string) $value : ''), $row),
                    fn (string $value): bool => $value !== '',
                );
                $lines[] = $this->analyze->handle(MappedRow::fromArray($row), $context)->copyValues($batch->id, $nextRow + $offset, $raw);
            }

            $this->copy->handle('staged_rows', AnalyzedRow::COPY_COLUMNS, $lines);
            $this->duplicates->handle($batch);
            $this->recount->handle($batch);
            $batch->update(['status' => ImportBatchStatus::Ready]);
        });

        return $batch->refresh();
    }

    private function newBatch(StageContactsData $data): ImportBatch
    {
        $defaultName = $data->sourceName ?? $data->label;
        $sourceId = $this->sources->find($defaultName);

        return ImportBatch::query()->create([
            'source_id' => $sourceId,
            'source_name' => $sourceId === null ? mb_substr($defaultName, 0, 120) : null,
            'origin' => ImportOrigin::Mcp,
            'user_id' => $data->userId,
            'status' => ImportBatchStatus::Analyzing,
            'original_filename' => $data->label,
        ]);
    }

    private function existingBatch(StageContactsData $data): ImportBatch
    {
        $batch = ImportBatch::query()->find($data->batchId);

        if ($batch === null || $batch->origin !== ImportOrigin::Mcp || $batch->user_id !== $data->userId || $batch->status !== ImportBatchStatus::Ready) {
            throw ValidationException::withMessages([
                'batch_id' => 'You can only add rows to your own MCP batch while it is ready for review.',
            ]);
        }

        return $batch;
    }
}
