<?php

namespace App\Jobs;

use App\Actions\Database\CopyRowsAction;
use App\Actions\Imports\AnalyzeRowAction;
use App\Actions\Imports\DetectDuplicatesAction;
use App\Actions\Imports\ReadImportFileAction;
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
use RuntimeException;
use Throwable;

/**
 * Reads the uploaded file, maps and normalizes every row in PHP, bulk-loads them into staged_rows with COPY
 * (5,000 at a time), then runs the set-based duplicate and do-not-contact checks and counts the results.
 */
class AnalyzeBatchJob implements ShouldQueue
{
    use Queueable;

    public const int CHUNK = 5_000;

    public const int MAX_ROWS = 500_000;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public int $batchId) {}

    public function handle(
        ReadImportFileAction $read,
        AnalyzeRowAction $analyze,
        CopyRowsAction $copy,
        DetectDuplicatesAction $duplicates,
        RecountBatchAction $recount,
        DataSettings $settings,
    ): void {
        $batch = ImportBatch::query()->with('source')->findOrFail($this->batchId);
        $file = $batch->getFirstMedia('file') ?? throw new RuntimeException('The uploaded file is missing.');
        $headers = $batch->headers ?? [];
        $columnMap = $batch->column_map ?? [];
        $context = new AnalysisContext($batch->source?->collected_year, $settings->stale_data_year_threshold);

        DB::table('staged_rows')->where('import_batch_id', $batch->id)->delete();

        $buffer = [];
        $rowNumber = 0;
        $dataRows = 0;

        foreach ($read->handle($file->getPath(), pathinfo($file->file_name, PATHINFO_EXTENSION)) as $cells) {
            $rowNumber++;
            if ($rowNumber === 1) {
                continue; // header row
            }

            $raw = [];
            foreach ($headers as $index => $header) {
                $value = MappedRow::cell($cells[$index] ?? null);
                if ($value !== '') {
                    $raw[$header] = $value;
                }
            }
            if ($raw === []) {
                continue; // empty row
            }

            if (++$dataRows > self::MAX_ROWS) {
                throw new RuntimeException('The file has more than '.number_format(self::MAX_ROWS).' rows. Split it into smaller files.');
            }

            $analyzed = $analyze->handle(MappedRow::fromCells($headers, $columnMap, $cells), $context);
            $buffer[] = $analyzed->copyValues($batch->id, $rowNumber, $raw);

            if (count($buffer) >= self::CHUNK) {
                $copy->handle('staged_rows', AnalyzedRow::COPY_COLUMNS, $buffer);
                $buffer = [];
            }
        }

        $copy->handle('staged_rows', AnalyzedRow::COPY_COLUMNS, $buffer);

        $duplicates->handle($batch);
        $recount->handle($batch);
        $batch->update(['status' => ImportBatchStatus::Ready, 'error' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        ImportBatch::query()->whereKey($this->batchId)->update([
            'status' => ImportBatchStatus::Failed,
            'error' => $exception?->getMessage() ?? 'The analysis failed.',
        ]);
    }
}
