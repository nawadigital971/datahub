<?php

namespace App\Queries\Imports;

use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Models\StagedRow;
use App\Queries\Audience\AudienceLookups;
use Illuminate\Support\Facades\DB;

/**
 * Incoming row vs an existing contact, field by field, for the merge panel. Also lists every contact
 * the row matches (more than one = conflict) so the reviewer can pick which one to merge into.
 *
 * @phpstan-type Field array{field: string, label: string, incoming: string|null, existing: string|null, choice: 'incoming'|'existing'}
 * @phpstan-type Comparison array{candidates: list<array{id: int, label: string, matched_by: string}>, contact_id: int|null, fields: list<Field>}
 */
class MergeComparisonQuery
{
    /** Contact fields a reviewer can choose between (phones and emails are always added, never replaced). */
    public const array FIELDS = [
        'name_en' => 'English name',
        'name_ar' => 'Arabic name',
        'company_name' => 'Company',
        'gender' => 'Gender',
        'nationality_country_id' => 'Nationality',
        'region_id' => 'Emirate',
        'area_id' => 'Area',
        'sector_id' => 'Sector',
        'address_raw' => 'Address',
        'mobile_e164' => 'Primary mobile',
        'email' => 'Primary email',
    ];

    public function __construct(private AudienceLookups $lookups) {}

    /**
     * @return Comparison
     */
    public function handle(StagedRow $row, ?int $contactId, bool $mask): array
    {
        $candidates = $this->candidates($row);
        $ids = array_column($candidates, 'id');
        $contactId = in_array($contactId, $ids, true) ? $contactId : ($row->matched_contact_id ?? $ids[0] ?? null);
        $contact = $contactId !== null ? Contact::query()->find($contactId) : null;

        $fields = [];
        foreach (self::FIELDS as $field => $label) {
            $incoming = $this->display($field, $row->{$field}, false);
            $existing = $contact !== null ? $this->display($field, $contact->{$field}, $mask) : null;
            $stored = $row->merge_choices[$field] ?? null;

            $fields[] = [
                'field' => $field,
                'label' => $label,
                'incoming' => $incoming,
                'existing' => $existing,
                'choice' => in_array($stored, ['incoming', 'existing'], true) ? $stored : ($existing === null ? 'incoming' : 'existing'),
            ];
        }

        return ['candidates' => $candidates, 'contact_id' => $contactId, 'fields' => $fields];
    }

    /**
     * Contacts sharing a phone (first) or the email with this row.
     *
     * @return list<array{id: int, label: string, matched_by: string}>
     */
    public function candidates(StagedRow $row): array
    {
        $phones = array_values(array_filter([$row->mobile_e164, $row->landline_e164]));
        $byPhone = $phones === [] ? [] : DB::table('contact_phones')->whereIn('e164', $phones)->pluck('contact_id')->all();
        $byEmail = $row->email === null ? [] : DB::table('contact_emails')->where('email', $row->email)->pluck('contact_id')->all();

        $matchedBy = [];
        foreach ($byPhone as $id) {
            $matchedBy[(int) $id] = 'phone';
        }
        foreach ($byEmail as $id) {
            $matchedBy[(int) $id] ??= 'email';
        }

        return array_values(Contact::query()->whereIn('id', array_keys($matchedBy))->get(['id', 'name_en', 'name_ar', 'company_name'])
            ->map(fn (Contact $contact): array => [
                'id' => $contact->id,
                'label' => $contact->company_name ?? $contact->name_en ?? $contact->name_ar ?? "Contact #{$contact->id}",
                'matched_by' => $matchedBy[$contact->id],
            ])->all());
    }

    private function display(string $field, mixed $value, bool $mask): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = $value instanceof \BackedEnum ? $value->value : $value;

        return match ($field) {
            'gender' => $value === 'm' ? 'Male' : ($value === 'f' ? 'Female' : (string) $value),
            'nationality_country_id' => $this->lookups->label('country', (int) $value),
            'region_id' => $this->lookups->label('region', (int) $value),
            'area_id' => $this->lookups->label('area', (int) $value),
            'sector_id' => $this->lookups->label('sector', (int) $value),
            'mobile_e164' => $mask ? ContactResource::maskPhone((string) $value) : (string) $value,
            'email' => $mask ? ContactResource::maskEmail((string) $value) : (string) $value,
            default => (string) $value,
        };
    }
}
