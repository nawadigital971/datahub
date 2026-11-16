<?php

namespace App\Http\Requests\Health;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class ShowDataHealthRequest extends FormRequest
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

    /**
     * Whether the email verifier panel (credits, recent tasks, Verify emails) is shown.
     */
    public function canVerifyEmails(): bool
    {
        return $this->user()?->can(Permission::ContactsVerifyEmails->value) ?? false;
    }
}
