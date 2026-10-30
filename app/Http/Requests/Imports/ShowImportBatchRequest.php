<?php

namespace App\Http\Requests\Imports;

use App\Enums\Permission;
use App\Queries\Imports\StagedRowFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Viewing imports (list, a batch's preview, the mapping screen): imports.create or imports.review.
 */
class ShowImportBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->can(Permission::ImportsReview->value) || $user->can(Permission::ImportsCreate->value));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array'],
            'filter.*' => ['nullable', 'string', 'max:500'],
            'filter.status' => ['nullable', 'integer'],
            'filter.source' => ['nullable', 'integer'],
            'cursor' => ['nullable', 'string', 'max:500'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', 'in:row_number,-row_number,created_at,-created_at'],
            'contact_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function stagedRowFilters(): array
    {
        $filters = $this->validated('filter', []);

        return is_array($filters) ? array_intersect_key($filters, array_flip(StagedRowFilters::KEYS)) : [];
    }
}
