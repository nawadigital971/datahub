<?php

namespace App\Http\Requests\Health;

use App\Actions\EmailVerification\SubmitEmailVerificationTaskAction;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class StoreEmailVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ContactsVerifyEmails->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'count' => ['required', 'integer', 'min:1', 'max:'.SubmitEmailVerificationTaskAction::MAX_PER_TASK],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'count.max' => 'The verifier takes up to :max emails per task.',
        ];
    }
}
