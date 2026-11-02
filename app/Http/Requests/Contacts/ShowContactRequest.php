<?php

namespace App\Http\Requests\Contacts;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class ShowContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ContactsView->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
