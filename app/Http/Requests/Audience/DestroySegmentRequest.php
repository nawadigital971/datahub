<?php

namespace App\Http\Requests\Audience;

use App\Enums\Permission;
use App\Models\Segment;
use Illuminate\Foundation\Http\FormRequest;

class DestroySegmentRequest extends FormRequest
{
    /**
     * Only the owner may delete a segment, and only with segments.manage.
     */
    public function authorize(): bool
    {
        $segment = $this->route('segment');

        return $segment instanceof Segment
            && $this->user()?->can(Permission::SegmentsManage->value)
            && $segment->user_id === $this->user()->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
