<?php

namespace App\Http\Requests\Exports;

use App\Enums\ExportChannel;
use App\Enums\Permission;
use App\Http\Requests\Audience\ValidatesAudienceFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreExportRequest extends FormRequest
{
    use ValidatesAudienceFilters;

    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ExportsCreate->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', 'string', 'in:'.implode(',', array_map(fn (ExportChannel $channel): string => $channel->key(), ExportChannel::cases()))],
            'hashed' => ['boolean'],
            'purpose' => ['required', 'string', 'min:10', 'max:500'],
            ...$this->audienceFilterRules('filters', required: true),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'purpose.required' => 'Say what this export is for. Every export is logged with its purpose.',
            'purpose.min' => 'Describe the purpose in a few more words (at least 10 characters).',
        ];
    }
}
