<?php

namespace App\Http\Requests\Imports;

use App\Models\StagedRow;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStagedRowRequest extends FormRequest
{
    use ReviewsImportBatch;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $row = $this->route('row');

        return [
            'field' => ['required', 'string', 'in:'.implode(',', StagedRowFieldRules::FIELDS)],
            'value' => StagedRowFieldRules::for($this->string('field')->toString(), $row instanceof StagedRow ? $row : null),
        ];
    }
}
