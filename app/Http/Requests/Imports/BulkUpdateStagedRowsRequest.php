<?php

namespace App\Http\Requests\Imports;

use App\Queries\Imports\StagedRowFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateStagedRowsRequest extends FormRequest
{
    use ReviewsImportBatch;

    public const array OPERATIONS = ['set_action', 'set_region', 'set_area', 'set_sector', 'set_source', 'accept_suggestions'];

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $valueRules = match ($this->input('operation')) {
            'set_action' => ['required', 'in:create,merge,skip'],
            'set_region' => ['nullable', 'integer', 'exists:regions,id'],
            'set_area' => ['required', 'integer', 'exists:areas,id'],
            'set_sector' => ['nullable', 'integer', 'exists:sectors,id'],
            'set_source' => ['required', 'string', 'max:120'],
            default => ['nullable'],
        };

        return [
            'operation' => ['required', 'in:'.implode(',', self::OPERATIONS)],
            'value' => $valueRules,
            'ids' => ['required_without:all_matching', 'array', 'max:1000'],
            'ids.*' => ['integer'],
            'all_matching' => ['boolean'],
            'filter' => ['sometimes', 'array:'.implode(',', StagedRowFilters::KEYS)],
            'filter.*' => ['nullable', 'string', 'max:500'],
        ];
    }
}
