<?php

namespace App\Http\Requests\Contacts;

use App\Enums\ContactType;
use App\Enums\DncReason;
use App\Enums\Gender;

/**
 * Contact fields that can be corrected one by one (by Claude over MCP). Phones and emails are not
 * here: they identify contacts, so they change through imports and merges.
 */
class ContactFieldRules
{
    public const array FIELDS = [
        'name_en', 'name_ar', 'company_name', 'gender', 'type', 'region_id', 'area_id', 'sector_id',
        'nationality_country_id', 'is_junk', 'do_not_contact',
    ];

    /**
     * @return list<mixed>
     */
    public static function for(string $field): array
    {
        return match ($field) {
            'name_en', 'name_ar', 'company_name' => ['nullable', 'string', 'max:200'],
            'gender' => ['nullable', 'in:'.implode(',', array_column(Gender::cases(), 'value'))],
            'type' => ['required', 'integer', 'in:'.implode(',', array_column(ContactType::cases(), 'value'))],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'sector_id' => ['nullable', 'integer', 'exists:sectors,id'],
            'nationality_country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'is_junk' => ['required', 'boolean'],
            'do_not_contact' => ['nullable', 'integer', 'in:'.implode(',', array_column(DncReason::cases(), 'value'))],
            default => ['prohibited'],
        };
    }

    /**
     * Turn the text Claude sends ("12", "true", "", "f") into the value the rules check.
     */
    public static function cast(string $field, mixed $value): mixed
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return $field === 'is_junk' ? false : null;
        }
        if (in_array($field, ['type', 'region_id', 'area_id', 'sector_id', 'nationality_country_id', 'do_not_contact'], true) && is_numeric($value)) {
            return (int) $value;
        }
        if ($field === 'is_junk') {
            return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $value;
        }

        return is_string($value) ? trim($value) : $value;
    }
}
