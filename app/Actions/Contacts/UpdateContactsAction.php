<?php

namespace App\Actions\Contacts;

use App\DTOs\Contacts\ContactEditData;
use App\Enums\ContactEventType;
use App\Enums\DncReason;
use App\Http\Requests\Contacts\ContactFieldRules;
use App\Jobs\RefreshFacetCountsJob;
use App\Models\Area;
use App\Models\Contact;
use App\Models\ContactEvent;
use BackedEnum;
use Illuminate\Support\Facades\DB;

class UpdateContactsAction
{
    public function __construct(private BustAudienceCacheAction $bustCache) {}

    /**
     * Apply field corrections, log each one in the contact's history and refresh the counts.
     * A replaced English/Arabic name is kept in other_names, so a name is never lost.
     *
     * @param  list<ContactEditData>  $edits
     * @return array<int, Contact> updated contacts by id
     */
    public function handle(array $edits, ?int $userId, string $via): array
    {
        $contacts = Contact::query()->whereKey(array_unique(array_map(fn (ContactEditData $edit): int => $edit->contactId, $edits)))->get()->keyBy('id');

        DB::transaction(function () use ($edits, $contacts, $userId, $via): void {
            foreach ($edits as $edit) {
                $contact = $contacts->get($edit->contactId);
                if ($contact === null) {
                    continue;
                }

                if (in_array($edit->field, ContactFieldRules::OTHER_NAME_FIELDS, true)) {
                    $from = implode(', ', $contact->other_names);
                    $this->changeOtherNames($contact, $edit);
                    $to = implode(', ', $contact->other_names);
                    if ($from === $to) {
                        continue;
                    }
                } else {
                    $from = $this->plain($contact->getAttribute($edit->field));
                    $this->apply($contact, $edit);
                    $to = $this->plain($contact->getAttribute($edit->field));
                    if (! $contact->isDirty()) {
                        continue;
                    }

                    $contact->save();
                    if (in_array($edit->field, ['name_en', 'name_ar'], true) && is_string($from)) {
                        $this->keepOtherNames($contact, [$from]);
                    }
                }
                ContactEvent::query()->create([
                    'contact_id' => $contact->id,
                    'type' => $edit->field === 'do_not_contact' && $contact->do_not_contact ? ContactEventType::OptOut : ContactEventType::Edit,
                    'occurred_at' => now(),
                    'meta' => ['field' => $edit->field, 'from' => $from, 'to' => $to, 'via' => $via],
                    'user_id' => $userId,
                ]);
            }
        });

        $this->bustCache->handle();
        RefreshFacetCountsJob::dispatch()->afterCommit();

        return $contacts->all();
    }

    private function apply(Contact $contact, ContactEditData $edit): void
    {
        match ($edit->field) {
            'do_not_contact' => $contact->fill([
                'do_not_contact' => $edit->value !== null,
                'dnc_reason' => $edit->value === null ? null : DncReason::from((int) $edit->value),
            ]),
            'area_id' => $contact->fill([
                'area_id' => $edit->value,
                'region_id' => $edit->value === null ? $contact->region_id : Area::query()->whereKey($edit->value)->value('region_id'),
            ]),
            'name_en' => $contact->fill(['name_en' => $edit->value, 'name_en_verified' => $edit->value !== null]),
            default => $contact->fill([$edit->field => $edit->value]),
        };
    }

    /**
     * Add or remove one name in the contact's other names (spelling variants of a known name are not added twice).
     */
    private function changeOtherNames(Contact $contact, ContactEditData $edit): void
    {
        if ($edit->field === 'add_other_name') {
            $this->keepOtherNames($contact, [(string) $edit->value]);

            return;
        }

        $this->storeOtherNames($contact, DB::scalar(<<<'SQL'
            UPDATE contacts SET updated_at = now(), other_names = ARRAY(
                SELECT n FROM unnest(other_names) WITH ORDINALITY AS u(n, ord)
                WHERE nawaa_normalize_text(n) IS DISTINCT FROM nawaa_normalize_text(?)
                ORDER BY ord
            )
            WHERE id = ? RETURNING other_names
            SQL, [(string) $edit->value, $contact->id]));
    }

    /**
     * Merge names into other_names with the same rules as imports (deduped, never the main names).
     *
     * @param  list<string>  $names
     */
    private function keepOtherNames(Contact $contact, array $names): void
    {
        $this->storeOtherNames($contact, DB::scalar(<<<'SQL'
            UPDATE contacts SET updated_at = now(),
                other_names = nawaa_merge_names(other_names, ARRAY(SELECT jsonb_array_elements_text(?::jsonb)), name_en, name_ar)
            WHERE id = ? RETURNING other_names
            SQL, [json_encode($names, JSON_UNESCAPED_UNICODE), $contact->id]));
    }

    private function storeOtherNames(Contact $contact, mixed $otherNames): void
    {
        $contact->setRawAttributes(array_merge($contact->getAttributes(), ['other_names' => $otherNames]), true);
    }

    private function plain(mixed $value): string|int|bool|null
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return is_string($value) || is_int($value) || is_bool($value) ? $value : null;
    }
}
