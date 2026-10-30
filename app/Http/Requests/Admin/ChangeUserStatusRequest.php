<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ChangeUserStatusRequest extends FormRequest
{
    use ManagesUsers;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
