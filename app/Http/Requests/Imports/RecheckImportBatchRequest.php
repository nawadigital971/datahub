<?php

namespace App\Http\Requests\Imports;

use App\Enums\Permission;
use App\Models\ImportBatch;
use Illuminate\Foundation\Http\FormRequest;

class RecheckImportBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $batch = $this->route('batch');

        return $batch instanceof ImportBatch
            && $batch->isRecheckable()
            && ($this->user()?->can(Permission::ImportsReview->value) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
