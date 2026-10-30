<?php

namespace App\Http\Requests\Audience;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSegmentRequest extends FormRequest
{
    use ValidatesAudienceFilters;

    public function authorize(): bool
    {
        return $this->user()?->can(Permission::SegmentsManage->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'is_shared' => ['boolean'],
            ...$this->audienceFilterRules('filters', required: true),
        ];
    }
}
