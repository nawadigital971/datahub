<?php

namespace App\Http\Requests\Imports;

use App\Enums\ImportBatchStatus;
use App\Enums\Permission;
use App\Models\ImportBatch;
use Illuminate\Foundation\Http\FormRequest;

class CommitImportBatchRequest extends FormRequest
{
    /**
     * A ready batch can be committed; so can one whose earlier commit failed half-way (it resumes).
     */
    public function authorize(): bool
    {
        $batch = $this->route('batch');

        return $batch instanceof ImportBatch
            && ($batch->status === ImportBatchStatus::Ready || ($batch->status === ImportBatchStatus::Failed && $batch->committed_by !== null))
            && ($this->user()?->can(Permission::ImportsCommit->value) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
