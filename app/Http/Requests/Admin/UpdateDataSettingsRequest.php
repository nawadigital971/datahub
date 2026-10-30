<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDataSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::SettingsUpdate->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $region = $this->input('default_phone_region');
        if (is_string($region)) {
            $this->merge(['default_phone_region' => strtoupper(trim($region))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'stale_data_year_threshold' => ['required', 'integer', 'between:2000,'.((int) date('Y') + 1)],
            'hash_on_export_default' => ['required', 'boolean'],
            'export_retention_days' => ['required', 'integer', 'between:1,3650'],
            'staged_retention_days' => ['required', 'integer', 'between:1,365'],
            'default_phone_region' => ['required', 'string', 'size:2', 'exists:countries,iso2'],
        ];
    }
}
