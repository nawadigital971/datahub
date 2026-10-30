<?php

namespace App\Http\Requests\Imports;

use App\Enums\ContactType;
use App\Enums\Gender;
use App\Models\StagedRow;
use App\Rules\E164Phone;
use Closure;

/**
 * Validation for one edited staged-row field. Shared by the preview's inline edits and the MCP
 * update_staged_rows tool, so both accept exactly the same values.
 */
class StagedRowFieldRules
{
    /** Columns a reviewer (or Claude, through MCP) may edit. */
    public const array FIELDS = [
        'name_en', 'name_ar', 'company_name', 'mobile_e164', 'landline_e164', 'email', 'gender', 'type',
        'region_id', 'area_id', 'sector_id', 'nationality_country_id', 'address_raw', 'source_name', 'action',
    ];

    /**
     * @return list<mixed>
     */
    public static function for(?string $field, ?StagedRow $row): array
    {
        return match ($field) {
            'name_en', 'name_ar', 'company_name' => ['nullable', 'string', 'max:200'],
            'address_raw' => ['nullable', 'string', 'max:2000'],
            'source_name' => ['nullable', 'string', 'max:120'],
            'mobile_e164', 'landline_e164' => ['nullable', 'string', 'max:40', new E164Phone],
            'email' => ['nullable', 'string', 'max:254', 'email:rfc'],
            'gender' => ['nullable', 'in:'.implode(',', array_column(Gender::cases(), 'value'))],
            'type' => ['required', 'integer', 'in:'.implode(',', array_column(ContactType::cases(), 'value'))],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'sector_id' => ['nullable', 'integer', 'exists:sectors,id'],
            'nationality_country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'action' => [
                'required', 'string', 'in:create,merge,skip',
                function (string $attribute, mixed $value, Closure $fail) use ($row): void {
                    if ($value === 'merge' && $row?->matched_contact_id === null) {
                        $fail('This row does not match an existing contact, so it cannot be merged.');
                    }
                },
            ],
            default => ['nullable'],
        };
    }
}
