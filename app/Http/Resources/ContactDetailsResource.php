<?php

namespace App\Http\Resources;

use App\Enums\ContactEventType;
use App\Enums\ContactType;
use App\Enums\Permission;
use App\Models\Contact;
use App\Models\ContactEmail;
use App\Models\ContactEvent;
use App\Models\ContactPhone;
use App\Models\ContactSource;
use App\Queries\Audience\AudienceLookups;
use App\Queries\Contacts\ContactDetailsQuery;
use App\Queries\Imports\MergeComparisonQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Everything about one contact for the details panel: all names, phones, emails, sources with the
 * original row, and history. Phones, emails and free text are masked on the server unless the user
 * has contacts.view_unmasked.
 *
 * @mixin Contact
 */
class ContactDetailsResource extends JsonResource
{
    public static $wrap = null;

    private bool $masked = true;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $lookups = app(AudienceLookups::class);
        $this->masked = ! ($request->user()?->can(Permission::ContactsViewUnmasked->value) ?? false);
        $events = $this->events->take(ContactDetailsQuery::HISTORY_LIMIT);

        return [
            'id' => $this->id,
            'is_business' => $this->type === ContactType::Business,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'other_names' => $this->other_names,
            'company_name' => $this->company_name,
            'masked' => $this->masked,
            'details' => array_values(array_filter([
                ['label' => 'Type', 'value' => $this->type->label()],
                ['label' => 'Gender', 'value' => $this->gender?->label()],
                ['label' => 'Nationality', 'value' => $this->nationality_country_id ? $lookups->label('country', $this->nationality_country_id) : null],
                ['label' => 'Country', 'value' => $this->country_id ? $lookups->label('country', $this->country_id) : null],
                ['label' => 'Emirate', 'value' => $this->region_id ? $lookups->label('region', $this->region_id) : null],
                ['label' => 'Area', 'value' => $this->area_id ? $lookups->label('area', $this->area_id) : null],
                ['label' => 'Sector', 'value' => $this->sector_id ? $lookups->label('sector', $this->sector_id) : null],
                ['label' => 'Address', 'value' => $this->address_raw === null ? null : $this->text($this->address_raw)],
                ['label' => 'Seen in', 'value' => $this->years()],
                ['label' => 'Last call', 'value' => $this->last_call_outcome?->label()],
            ], fn (array $row): bool => $row['value'] !== null && $row['value'] !== '')),
            'phones' => $this->phones->map(fn (ContactPhone $phone): array => [
                'value' => $this->masked ? ContactResource::maskPhone($phone->e164) : $phone->e164,
                'type' => $phone->type->label(),
                'is_primary' => $phone->is_primary,
            ])->values()->all(),
            'emails' => $this->emails->map(fn (ContactEmail $email): array => [
                'value' => $this->masked ? ContactResource::maskEmail($email->email) : $email->email,
                'is_primary' => $email->is_primary,
                'status' => $email->email === $this->email ? $this->email_status->label() : null,
            ])->values()->all(),
            'status' => [
                'do_not_contact' => $this->do_not_contact,
                'dnc_reason' => $this->dnc_reason?->label(),
                'is_junk' => $this->is_junk,
                'check_spelling' => $this->name_en !== null && ! $this->name_en_verified,
            ],
            'extra' => collect($this->extra ?? [])
                ->map(fn (mixed $value, string|int $key): array => [
                    'label' => str_replace('_', ' ', (string) $key),
                    'value' => $this->text(is_array($value) ? implode(', ', array_map(strval(...), $value)) : (string) $value),
                ])->values()->all(),
            'sources' => $this->sourceLinks->map(fn (ContactSource $link): array => [
                'source' => $link->source->name,
                'year' => $link->seen_year,
                'file' => $link->importBatch?->original_filename,
                'import_batch_id' => $link->import_batch_id,
                'row_number' => $link->row_number,
                'added_at' => $link->created_at?->toIso8601String(),
                'raw' => collect($link->raw)
                    ->sortBy(fn (mixed $value, string|int $column): int => array_flip($link->importBatch->headers ?? [])[(string) $column] ?? PHP_INT_MAX)
                    ->map(fn (mixed $value, string|int $column): array => [
                        'column' => (string) $column,
                        'value' => $value === null ? null : $this->text(is_scalar($value) ? (string) $value : (string) json_encode($value, JSON_UNESCAPED_UNICODE)),
                    ])->values()->all(),
            ])->values()->all(),
            'history' => $events->map(fn (ContactEvent $event): array => [
                'type' => $event->type->label(),
                'at' => $event->occurred_at->toIso8601String(),
                'by' => $event->user?->name ?? ($event->agent === null ? null : $this->text($event->agent)),
                'text' => $this->describe($event),
            ])->values()->all(),
            'history_more' => $this->events->count() > ContactDetailsQuery::HISTORY_LIMIT,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function years(): ?string
    {
        if ($this->first_seen_year === null && $this->last_seen_year === null) {
            return null;
        }
        if ($this->first_seen_year === $this->last_seen_year || $this->first_seen_year === null || $this->last_seen_year === null) {
            return (string) ($this->last_seen_year ?? $this->first_seen_year);
        }

        return "{$this->first_seen_year}–{$this->last_seen_year}";
    }

    /**
     * One readable line for a history entry.
     */
    private function describe(ContactEvent $event): string
    {
        $meta = $event->meta ?? [];
        $row = isset($meta['import_batch_id']) ? "import #{$meta['import_batch_id']}, row {$meta['row_number']}" : null;

        return match ($event->type) {
            ContactEventType::Merge => 'Merged with '.($row ?? 'an imported row'),
            ContactEventType::Call => trim(($event->outcome?->label() ?? 'Call').(isset($meta['notes']) && $meta['notes'] !== '' ? ' — '.$this->text((string) $meta['notes']) : '').($row ? " ({$row})" : '')),
            ContactEventType::OptOut => isset($meta['field'])
                ? $this->describeEdit($meta)
                : 'Added to do-not-contact'.(isset($meta['reason']) ? ": {$meta['reason']}" : '').(isset($meta['value']) ? ' ('.$this->text((string) $meta['value']).')' : ''),
            ContactEventType::Edit => $this->describeEdit($meta),
            ContactEventType::Export => 'Included in an export',
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function describeEdit(array $meta): string
    {
        $field = (string) ($meta['field'] ?? '');
        $label = MergeComparisonQuery::FIELDS[$field] ?? ucfirst(str_replace('_', ' ', $field));
        $value = fn (mixed $value): string => $value === null || $value === '' ? '(empty)' : $this->text(is_scalar($value) ? (string) $value : (string) json_encode($value));

        return "{$label}: {$value($meta['from'] ?? null)} → {$value($meta['to'] ?? null)}".(isset($meta['via']) && $meta['via'] === 'mcp' ? ' (via Claude)' : '');
    }

    private function text(string $text): string
    {
        return $this->masked ? ContactResource::maskText($text) : $text;
    }
}
