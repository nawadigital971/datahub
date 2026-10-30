<?php

namespace App\Queries\ReferenceData\Types;

use App\Actions\Normalization\NormalizeEmailAction;
use App\Actions\Normalization\NormalizePhoneAction;
use App\Enums\ContactEventType;
use App\Enums\DncReason;
use App\Enums\Permission;
use App\Enums\SuppressionKind;
use App\Models\Suppression;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * @extends ReferenceType<Suppression>
 */
class DoNotContactType extends ReferenceType
{
    public function __construct(private NormalizePhoneAction $phones, private NormalizeEmailAction $emails) {}

    public function key(): string
    {
        return 'do_not_contact';
    }

    public function label(): string
    {
        return 'Do-not-contact';
    }

    public function description(): string
    {
        return 'Phone numbers and emails that must never be contacted. Adding one marks every matching contact as do-not-contact (logged as an opt-out) and leaves it out of all exports; future imports flag it too. Removing a value from this list does not clear contacts already marked.';
    }

    public function permission(): Permission
    {
        return Permission::ContactsUpdate;
    }

    protected function model(): string
    {
        return Suppression::class;
    }

    public function fields(): array
    {
        return [
            self::field('kind', 'Kind', 'select', true, null, array_map(fn (SuppressionKind $kind): array => ['value' => $kind->value, 'label' => $kind->label()], SuppressionKind::cases())),
            self::field('value', 'Phone or email', 'text', true, 'Any phone format works; it is stored in international format.'),
            self::field('reason', 'Reason', 'select', true, null, array_map(fn (DncReason $reason): array => ['value' => $reason->value, 'label' => $reason->label()], DncReason::cases())),
        ];
    }

    public function rules(?Model $item, array $values): array
    {
        $kind = SuppressionKind::tryFrom(is_numeric($values['kind'] ?? null) ? (int) $values['kind'] : 0);

        return [
            'kind' => ['required', 'integer', Rule::enum(SuppressionKind::class)],
            'reason' => ['required', 'integer', Rule::enum(DncReason::class)],
            'value' => ['required', 'string', 'max:254', function (string $attribute, mixed $value, Closure $fail) use ($kind, $item): void {
                $normalized = $kind === null ? null : $this->normalize($kind, is_scalar($value) ? (string) $value : '');
                if ($kind !== null && $normalized === null) {
                    $fail($kind === SuppressionKind::Phone ? 'This is not a valid phone number.' : 'This is not a valid email address.');

                    return;
                }
                if ($normalized !== null && Suppression::query()->where('value', $normalized)->whereKeyNot($item?->getKey() ?? 0)->exists()) {
                    $fail("{$normalized} is already on the do-not-contact list.");
                }
            }],
        ];
    }

    public function values(Model $item): array
    {
        return ['kind' => $item->kind->value, 'value' => $item->value, 'reason' => $item->reason->value];
    }

    public function fill(Model $item, array $values): void
    {
        $kind = SuppressionKind::from((int) $values['kind']);
        $item->kind = $kind;
        $item->value = $this->normalize($kind, (string) $values['value']) ?? (string) $values['value'];
        $item->reason = DncReason::from((int) $values['reason']);
    }

    /**
     * Mark every contact with this phone or email as do-not-contact and log the opt-out.
     */
    public function saved(Model $item, ?int $userId): void
    {
        if ($item->user_id === null && $userId !== null) {
            $item->user_id = $userId;
            $item->saveQuietly();
        }

        DB::statement(<<<'SQL'
            WITH matched AS (
                SELECT id FROM contacts WHERE mobile_e164 = ? OR landline_e164 = ? OR email = ?
                UNION SELECT contact_id FROM contact_phones WHERE e164 = ?
                UNION SELECT contact_id FROM contact_emails WHERE email = ?
            ), marked AS (
                UPDATE contacts c SET do_not_contact = true, dnc_reason = ?::smallint, updated_at = now()
                FROM matched m WHERE c.id = m.id AND c.do_not_contact = false
                RETURNING c.id
            )
            INSERT INTO contact_events (contact_id, type, occurred_at, meta, user_id)
            SELECT id, ?::smallint, now(), jsonb_build_object('value', ?::text, 'reason', ?::text), ?::bigint FROM marked
            SQL, [
            $item->value, $item->value, $item->value, $item->value, $item->value,
            $item->reason->value,
            ContactEventType::OptOut->value, $item->value, $item->reason->label(), $userId,
        ]);
    }

    public function title(Model $item): string
    {
        return $item->value;
    }

    public function search(Builder $query, string $term): void
    {
        $query->where('value', 'ILIKE', '%'.str_replace(['%', '_'], ['\%', '\_'], mb_strtolower(trim($term))).'%');
    }

    public function sorts(): array
    {
        return ['id', 'value', 'created_at'];
    }

    public function defaultSort(): string
    {
        return '-id';
    }

    private function normalize(SuppressionKind $kind, string $value): ?string
    {
        if ($kind === SuppressionKind::Phone) {
            $phone = $this->phones->handle($value)[0] ?? null;

            return $phone?->e164;
        }

        $email = $this->emails->handle($value)[0] ?? null;

        return $email !== null && $email->valid ? $email->email : null;
    }
}
