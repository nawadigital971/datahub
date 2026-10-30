<?php

namespace App\Jobs;

use App\Actions\Database\CopyRowsAction;
use App\Actions\Imports\AnalyzeRowAction;
use App\Actions\Imports\DetectDuplicatesAction;
use App\Actions\Imports\RecountBatchAction;
use App\DTOs\Imports\AnalysisContext;
use App\DTOs\Imports\AnalyzedRow;
use App\DTOs\Imports\MappedRow;
use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use App\Settings\DataSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Re-checks a batch staged over MCP (no file behind it) with the current cleaning rules: every row is
 * rebuilt from the values Claude sent, analysed again and re-loaded, then duplicates are detected again.
 */
class ReanalyzeStagedRowsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public int $batchId) {}

    public function handle(
        AnalyzeRowAction $analyze,
        CopyRowsAction $copy,
        DetectDuplicatesAction $duplicates,
        RecountBatchAction $recount,
        DataSettings $settings,
    ): void {
        $batch = ImportBatch::query()->with('source')->findOrFail($this->batchId);
        $context = new AnalysisContext($batch->source?->collected_year, $settings->stale_data_year_threshold);

        DB::transaction(function () use ($batch, $analyze, $copy, $context): void {
            $lines = [];
            foreach (DB::table('staged_rows')->where('import_batch_id', $batch->id)->orderBy('row_number')->get(['row_number', 'raw']) as $row) {
                /** @var array<string, mixed> $raw */
                $raw = json_decode((string) $row->raw, true) ?: [];
                $lines[] = $analyze->handle(MappedRow::fromArray($raw), $context)->copyValues($batch->id, (int) $row->row_number, $raw);
            }

            DB::table('staged_rows')->where('import_batch_id', $batch->id)->delete();
            foreach (array_chunk($lines, AnalyzeBatchJob::CHUNK) as $chunk) {
                $copy->handle('staged_rows', AnalyzedRow::COPY_COLUMNS, $chunk);
            }
        });

        $duplicates->handle($batch);
        $recount->handle($batch);
        $batch->update(['status' => ImportBatchStatus::Ready, 'error' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        ImportBatch::query()->whereKey($this->batchId)->update([
            'status' => ImportBatchStatus::Failed,
            'error' => $exception?->getMessage() ?? 'The re-check failed.',
        ]);
    }
}
