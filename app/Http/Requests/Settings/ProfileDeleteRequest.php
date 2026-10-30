<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Models\Export;
use App\Models\ImportBatch;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProfileDeleteRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->currentPasswordRules(),
        ];
    }

    /**
     * Accounts with export or import history must stay, so the log keeps who did what.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();
                if ($user !== null && (Export::query()->where('user_id', $user->id)->exists() || ImportBatch::query()->where('user_id', $user->id)->exists())) {
                    $validator->errors()->add('password', 'Your account has exports or imports in the log, so it cannot be deleted. Ask an administrator to deactivate it instead.');
                }
            },
        ];
    }
}
