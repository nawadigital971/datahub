<?php

namespace App\Queries\ReferenceData\Types;

use App\Models\Contact;
use App\Models\Country;
use App\Models\Region;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * @extends ReferenceType<Country>
 */
class CountryType extends ReferenceType
{
    public function key(): string
    {
        return 'countries';
    }

    public function label(): string
    {
        return 'Countries';
    }

    public function description(): string
    {
        return 'Countries for nationality and phone numbers. Aliases are other spellings found in files (e.g. "Emirati", "إماراتي").';
    }

    protected function model(): string
    {
        return Country::class;
    }

    public function fields(): array
    {
        return [
            self::field('name', 'Name', 'translated', true),
            self::field('iso2', 'ISO code', 'text', true, 'Two letters, e.g. AE.'),
            self::field('dial_code', 'Dial code', 'text', true, 'e.g. 971'),
            self::field('aliases', 'Other spellings', 'aliases', false, 'Nationality words and spellings that mean this country.'),
        ];
    }

    public function rules(?Model $item, array $values): array
    {
        return [
            ...self::translatedRules(),
            'iso2' => ['required', 'string', 'size:2', 'alpha', Rule::unique('countries', 'iso2')->ignore($item?->getKey())],
            'dial_code' => ['required', 'string', 'regex:/^\+?\d{1,4}$/'],
            ...self::aliasRules(),
        ];
    }

    public function values(Model $item): array
    {
        return ['name' => $item->getTranslations('name'), 'iso2' => $item->iso2, 'dial_code' => $item->dial_code, 'aliases' => $item->aliases ?? []];
    }

    public function fill(Model $item, array $values): void
    {
        $item->setTranslations('name', self::translated($values['name'] ?? null));
        $item->iso2 = strtoupper((string) $values['iso2']);
        $item->dial_code = ltrim((string) $values['dial_code'], '+');
        $item->aliases = self::aliases($values['aliases'] ?? null);
    }

    public function title(Model $item): string
    {
        return self::name($item);
    }

    public function search(Builder $query, string $term): void
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $query->where(fn (Builder $inner) => $inner->whereRaw('name::text ILIKE ?', [$like])->orWhere('iso2', 'ILIKE', $like)->orWhereRaw('aliases::text ILIKE ?', [$like]));
    }

    public function sorts(): array
    {
        return ['id', 'iso2'];
    }

    public function deleteBlocker(Model $item): ?string
    {
        if (Region::query()->where('country_id', $item->id)->exists()) {
            return 'It still has emirates or regions. Delete or move them first.';
        }
        if (Contact::query()->where('country_id', $item->id)->orWhere('nationality_country_id', $item->id)->exists()) {
            return 'Contacts use this country.';
        }

        return null;
    }
}
