<?php

namespace App\Http\Requests\Exports;

use App\Enums\ExportChannel;
use App\Enums\Permission;
use App\Http\Requests\Audience\ValidatesAudienceFilters;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PreviewExportRequest extends FormRequest
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
            'hashed' => ['nullable', 'in:0,1,true,false'],
            ...$this->audienceFilterRules('filter'),
        ];
    }
}
