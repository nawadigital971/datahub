<?php

namespace App\Actions\Imports;

use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use App\Settings\DataSettings;
use Illuminate\Support\Facades\DB;

class PurgeStaleImportsAction
{
    public function __construct(private DataSettings $settings) {}

    /**
     * Delete staged rows and files of batches discarded (or failed) longer ago than the retention period.
     * The batch record itself stays as history.
     */
    public function handle(): int
    {
        $cutoff = now()->subDays($this->settings->staged_retention_days);
        $purged = 0;

        ImportBatch::query()
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->where('status', ImportBatchStatus::Discarded)->where('discarded_at', '<', $cutoff))
                ->orWhere(fn ($q) => $q->where('status', ImportBatchStatus::Failed)->where('updated_at', '<', $cutoff)))
            ->lazyById(100)
            ->each(function (ImportBatch $batch) use (&$purged): void {
                DB::table('staged_rows')->where('import_batch_id', $batch->id)->delete();
                $batch->clearMediaCollection('file');
                $purged++;
            });

        return $purged;
    }
}
