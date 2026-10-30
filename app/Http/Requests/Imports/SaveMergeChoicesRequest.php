<?php

namespace App\Http\Requests\Imports;

use App\Models\StagedRow;
use App\Queries\Imports\MergeComparisonQuery;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveMergeChoicesRequest extends FormRequest
{
    use ReviewsImportBatch;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contact_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $row = $this->route('row');
                    $candidates = $row instanceof StagedRow ? array_column(app(MergeComparisonQuery::class)->candidates($row), 'id') : [];
                    if (! in_array((int) $value, $candidates, true)) {
                        $fail('Choose one of the contacts this row matches.');
                    }
                },
            ],
            'choices' => ['present', 'array:'.implode(',', array_keys(MergeComparisonQuery::FIELDS))],
            'choices.*' => ['in:incoming,existing'],
        ];
    }
}
