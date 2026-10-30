<?php

namespace App\Http\Requests\Audience;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ListAudienceRequest extends FormRequest
{
    use ValidatesAudienceFilters;

    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ContactsView->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->audienceFilterRules('filter'),
            'cursor' => ['nullable', 'string', 'max:500'],
            'pos' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'in:id,-id'],
        ];
    }
}
