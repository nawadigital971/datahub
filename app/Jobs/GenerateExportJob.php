<?php

namespace App\Jobs;

use App\Actions\Exports\BuildExportRowAction;
use App\DTOs\AudienceFilters;
use App\Enums\ExportStatus;
use App\Models\Export;
use App\Queries\ExportRowsQuery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

/**
 * Streams an export to a CSV on disk in chunks of 5,000 (never the whole file in memory),
 * then attaches it to the export through Media Library.
 */
class GenerateExportJob implements ShouldQueue
{
    use Queueable;

    public const int CHUNK = 5_000;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public int $exportId) {}

    public function handle(ExportRowsQuery $rows, BuildExportRowAction $buildRow): void
    {
        $export = Export::query()->findOrFail($this->exportId);
        $export->update(['status' => ExportStatus::Running]);

        $path = $this->temporaryPath();
        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'wb') ?: throw new RuntimeException("Cannot write {$path}.");

        try {
            if ($export->channel->writesByteOrderMark()) {
                fwrite($handle, "\xEF\xBB\xBF");
            }

            fputcsv($handle, $export->channel->columns(), escape: '');

            $count = 0;
            $query = $rows->handle(AudienceFilters::fromArray($export->filters), $export->channel);

            foreach ($query->lazyById(self::CHUNK, 'id') as $row) {
                fputcsv($handle, $buildRow->handle($row, $export->channel, $export->hashed), escape: '');
                $count++;
            }
        } finally {
            fclose($handle);
        }

        $export->addMedia($path)
            ->usingFileName(sprintf('nawaa-%s-export-%d.csv', $export->channel->key(), $export->id))
            ->toMediaCollection('file');

        $export->update(['status' => ExportStatus::Done, 'row_count' => $count]);
    }

    public function failed(?Throwable $exception): void
    {
        Export::query()->whereKey($this->exportId)->update(['status' => ExportStatus::Failed]);
        File::delete($this->temporaryPath());
    }

    private function temporaryPath(): string
    {
        return storage_path("app/private/exports/tmp/export-{$this->exportId}.csv");
    }
}
