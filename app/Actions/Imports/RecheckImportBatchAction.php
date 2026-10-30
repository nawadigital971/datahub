<?php

namespace App\Actions\Imports;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportOrigin;
use App\Jobs\AnalyzeBatchJob;
use App\Jobs\ReanalyzeStagedRowsJob;
use App\Models\ImportBatch;
use Illuminate\Validation\ValidationException;

class RecheckImportBatchAction
{
    /**
     * Analyse a staged batch again with the current cleaning rules and reference data. Uploaded files are
     * re-read; MCP batches are rebuilt from the values that were sent. Inline edits and merge choices are lost.
     */
    public function handle(ImportBatch $batch): ImportBatch
    {
        if (! $batch->isRecheckable()) {
            throw ValidationException::withMessages(['batch' => "Batch {$batch->id} is {$batch->status->label()} and cannot be re-checked now."]);
        }

        $batch->update(['status' => ImportBatchStatus::Analyzing, 'error' => null]);

        if ($batch->origin === ImportOrigin::Upload) {
            AnalyzeBatchJob::dispatch($batch->id)->afterCommit();
        } else {
            ReanalyzeStagedRowsJob::dispatch($batch->id)->afterCommit();
        }

        return $batch;
    }
}
