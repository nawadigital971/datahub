<?php

namespace App\Http\Requests\Imports;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShowMergeComparisonRequest extends FormRequest
{
    use ReviewsImportBatch;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['contact_id' => ['nullable', 'integer']];
    }
}
