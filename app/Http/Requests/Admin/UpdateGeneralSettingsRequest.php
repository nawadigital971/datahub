<?php

namespace App\Http\Requests\Admin;

use App\Enums\Permission;
use App\Enums\Theme;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGeneralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::SettingsUpdate->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'site_name' => ['required', 'array:'.implode(',', (array) config('app.locales'))],
            'site_name.'.config('app.fallback_locale') => ['required', 'string', 'max:80'],
            'site_name.*' => ['nullable', 'string', 'max:80'],
            'default_theme' => ['required', 'in:'.implode(',', array_column(Theme::cases(), 'value'))],
            'logo_light' => ['nullable', 'file', 'max:2048', 'mimetypes:image/png,image/jpeg,image/webp,image/svg+xml'],
            'logo_dark' => ['nullable', 'file', 'max:2048', 'mimetypes:image/png,image/jpeg,image/webp,image/svg+xml'],
            'favicon' => ['nullable', 'file', 'max:512', 'mimetypes:image/png,image/x-icon,image/vnd.microsoft.icon,image/svg+xml'],
            'remove' => ['sometimes', 'array'],
            'remove.*' => ['in:logo_light,logo_dark,favicon'],
        ];
    }
}
