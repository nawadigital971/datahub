<?php

namespace App\Http\Requests\Imports;

use App\Enums\ImportBatchStatus;
use App\Enums\Permission;
use App\Models\ImportBatch;
use App\Models\StagedRow;

/**
 * Rows can be reviewed (edited, merged, bulk-changed) only while the batch is ready, by imports.review.
 */
trait ReviewsImportBatch
{
    public function authorize(): bool
    {
        $batch = $this->route('batch');
        $row = $this->route('row');

        return $batch instanceof ImportBatch
            && $batch->status === ImportBatchStatus::Ready
            && (! $row instanceof StagedRow || $row->import_batch_id === $batch->id)
            && ($this->user()?->can(Permission::ImportsReview->value) ?? false);
    }
}
