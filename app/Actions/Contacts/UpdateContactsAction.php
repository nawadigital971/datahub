<?php

namespace App\Actions\Contacts;

use App\DTOs\Contacts\ContactEditData;
use App\Enums\ContactEventType;
use App\Enums\DncReason;
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

                $from = $this->plain($contact->getAttribute($edit->field));
                $this->apply($contact, $edit);
                $to = $this->plain($contact->getAttribute($edit->field));
                if (! $contact->isDirty()) {
                    continue;
                }

                $contact->save();
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

    private function plain(mixed $value): string|int|bool|null
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return is_string($value) || is_int($value) || is_bool($value) ? $value : null;
    }
}
