<?php

namespace App\Actions\Imports;

use App\Enums\ImportBatchStatus;
use App\Jobs\CommitBatchJob;
use App\Models\ImportBatch;
use App\Models\User;

class CommitImportBatchAction
{
    /**
     * Start committing a reviewed batch in the background (commits are human-only).
     */
    public function handle(ImportBatch $batch, User $user): ImportBatch
    {
        $batch->update(['status' => ImportBatchStatus::Committing, 'committed_by' => $user->id, 'error' => null]);

        CommitBatchJob::dispatch($batch->id, $user->id)->afterCommit();

        return $batch;
    }
}
