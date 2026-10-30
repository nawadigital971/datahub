<?php

namespace App\Http\Requests\Sources;

use App\Enums\LawfulBasis;
use App\Enums\Permission;

trait SourceRules
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::SettingsUpdate->value) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'array:'.implode(',', (array) config('app.locales'))],
            'name.'.config('app.fallback_locale') => ['required', 'string', 'max:120'],
            'name.*' => ['nullable', 'string', 'max:120'],
            'source_group_id' => ['nullable', 'integer', 'exists:source_groups,id'],
            'collected_year' => ['nullable', 'integer', 'between:1990,'.((int) date('Y') + 1)],
            'lawful_basis' => ['required', 'integer', 'in:'.implode(',', array_column(LawfulBasis::cases(), 'value'))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
