<?php

namespace App\Http\Requests\ReferenceData;

use Illuminate\Foundation\Http\FormRequest;

class DeleteReferenceItemRequest extends FormRequest
{
    use ResolvesReferenceType;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
