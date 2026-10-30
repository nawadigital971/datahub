<?php

namespace App\Actions\Imports;

use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;

class DiscardImportBatchAction
{
    /**
     * Drop a batch without importing anything. Its staged rows and file are purged after the retention period.
     */
    public function handle(ImportBatch $batch): ImportBatch
    {
        $batch->update(['status' => ImportBatchStatus::Discarded, 'discarded_at' => now()]);

        return $batch;
    }
}
