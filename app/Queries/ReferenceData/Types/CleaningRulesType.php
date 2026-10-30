<?php

namespace App\Queries\ReferenceData\Types;

use App\Enums\CallOutcome;
use App\Enums\CleaningRuleType;
use App\Enums\ImportTarget;
use App\Models\CleaningRule;
use App\Models\Region;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * @extends ReferenceType<CleaningRule>
 */
class CleaningRulesType extends ReferenceType
{
    public function key(): string
    {
        return 'cleaning_rules';
    }

    public function label(): string
    {
        return 'Cleaning rules';
    }

    public function description(): string
    {
        $types = implode(' ', array_map(fn (CleaningRuleType $type): string => "{$type->value}: {$type->description()}", CleaningRuleType::cases()));

        return 'How imported files are read and cleaned. New rules apply to the next analysis; use "Re-check" on a batch to apply them to rows already staged. '.$types;
    }

    protected function model(): string
    {
        return CleaningRule::class;
    }

    public function fields(): array
    {
        return [
            self::field('type', 'Rule type', 'select', true, null, array_map(fn (CleaningRuleType $type): array => ['value' => $type->value, 'label' => $type->label()], CleaningRuleType::cases())),
            self::field('key', 'Looks for', 'text', true),
            self::field('value', 'Means', 'text', false, 'Not needed for junk names.'),
            self::field('position', 'Order', 'number', false, 'Lower numbers are checked first (matters for header hints).'),
            self::field('notes', 'Notes', 'textarea'),
        ];
    }

    public function rules(?Model $item, array $values): array
    {
        $type = CleaningRuleType::tryFrom(is_string($values['type'] ?? null) ? $values['type'] : '');

        return [
            'type' => ['required', 'string', Rule::enum(CleaningRuleType::class)],
            'key' => ['required', 'string', 'max:200', Rule::unique('cleaning_rules', 'key')->where('type', $type->value ?? '')->ignore($item?->getKey())],
            'value' => [$type?->needsValue() === true ? 'required' : 'nullable', 'string', 'max:200', $this->valueRule($type)],
            'position' => ['nullable', 'integer', 'between:0,100000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function values(Model $item): array
    {
        return ['type' => $item->type->value, 'key' => $item->key, 'value' => $item->value, 'position' => $item->position, 'notes' => $item->notes];
    }

    public function fill(Model $item, array $values): void
    {
        $type = CleaningRuleType::from((string) $values['type']);
        $key = trim((string) $values['key']);
        $value = is_scalar($values['value'] ?? null) && trim((string) $values['value']) !== '' ? trim((string) $values['value']) : null;

        $item->type = $type;
        $item->key = in_array($type, [CleaningRuleType::EmailDomainFix, CleaningRuleType::EmailTldFix, CleaningRuleType::FirstName, CleaningRuleType::GenderWord, CleaningRuleType::BusinessWord, CleaningRuleType::NoteLabel, CleaningRuleType::PhoneSeparator], true) ? mb_strtolower($key) : $key;
        $item->value = $type->needsValue() ? $value : null;
        $item->position = is_numeric($values['position'] ?? null) ? (int) $values['position'] : (int) CleaningRule::query()->where('type', $type)->max('position') + 1;
        $item->notes = is_string($values['notes'] ?? null) && trim($values['notes']) !== '' ? trim($values['notes']) : null;
    }

    public function title(Model $item): string
    {
        return "{$item->type->label()}: {$item->key}";
    }

    public function search(Builder $query, string $term): void
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $type = CleaningRuleType::tryFrom($term);
        $query->where(fn (Builder $inner) => $type !== null
            ? $inner->where('type', $type)
            : $inner->where('key', 'ILIKE', $like)->orWhere('value', 'ILIKE', $like)->orWhere('notes', 'ILIKE', $like));
    }

    public function sorts(): array
    {
        return ['id', 'type', 'key', 'position'];
    }

    public function defaultSort(): string
    {
        return 'type';
    }

    private function valueRule(?CleaningRuleType $type): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($type): void {
            $value = is_scalar($value) ? trim((string) $value) : '';
            $valid = match ($type) {
                CleaningRuleType::CallCode, CleaningRuleType::CallPhrase => CallOutcome::tryFromKey($value) !== null,
                CleaningRuleType::FirstName, CleaningRuleType::GenderWord => in_array($value, ['m', 'f'], true),
                CleaningRuleType::NoteLabel => in_array($value, CleaningRuleType::NOTE_LABEL_MEANINGS, true),
                CleaningRuleType::LandlinePrefix => Region::query()->where('slug', $value)->exists(),
                CleaningRuleType::HeaderHint => ImportTarget::isValid($value),
                default => true,
            };

            if (! $valid) {
                $fail(match ($type) {
                    CleaningRuleType::CallCode, CleaningRuleType::CallPhrase => 'Use one of: '.implode(', ', array_map(fn (CallOutcome $outcome): string => $outcome->key(), CallOutcome::cases())).'.',
                    CleaningRuleType::FirstName, CleaningRuleType::GenderWord => 'Use "m" or "f".',
                    CleaningRuleType::NoteLabel => 'Use one of: '.implode(', ', CleaningRuleType::NOTE_LABEL_MEANINGS).'.',
                    CleaningRuleType::LandlinePrefix => 'Use the slug of an emirate (e.g. dubai).',
                    default => 'Use a mapping target such as phone, email, name, region or notes.',
                });
            }
        };
    }
}
