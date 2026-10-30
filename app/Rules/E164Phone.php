<?php

namespace App\Rules;

use App\Actions\Normalization\NormalizePhoneAction;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A phone number libphonenumber accepts (UAE format assumed when there is no country code).
 */
class E164Phone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $results = is_string($value) ? app(NormalizePhoneAction::class)->handle($value) : [];

        if (count($results) !== 1 || ! $results[0]->isValid()) {
            $fail('Enter one valid phone number, e.g. 050 123 4567 or +966 50 123 4567.');
        }
    }
}
