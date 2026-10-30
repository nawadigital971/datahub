<?php

namespace App\Http\Requests\Imports;

use App\Enums\ImportBatchStatus;
use App\Enums\Permission;
use App\Models\ImportBatch;
use Illuminate\Foundation\Http\FormRequest;

class DiscardImportBatchRequest extends FormRequest
{
    public const array DISCARDABLE = [ImportBatchStatus::Mapping, ImportBatchStatus::Ready, ImportBatchStatus::Failed];

    public function authorize(): bool
    {
        $batch = $this->route('batch');

        return $batch instanceof ImportBatch
            && in_array($batch->status, self::DISCARDABLE, true)
            && ($this->user()?->can(Permission::ImportsDiscard->value) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
