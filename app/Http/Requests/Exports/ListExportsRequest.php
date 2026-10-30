<?php

namespace App\Http\Requests\Exports;

use App\Enums\ExportChannel;
use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ListExportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ExportsViewLog->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filter' => ['sometimes', 'array:channel,user'],
            'filter.channel' => ['nullable', 'string', 'in:'.implode(',', array_map(fn (ExportChannel $channel): string => $channel->key(), ExportChannel::cases()))],
            'filter.user' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'in:created_at,-created_at,row_count,-row_count'],
        ];
    }
}
