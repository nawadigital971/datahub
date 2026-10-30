<?php

namespace App\Queries\ReferenceData\Types;

use App\Models\Area;
use App\Models\Contact;
use App\Models\Country;
use App\Models\Region;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * @extends ReferenceType<Region>
 */
class RegionType extends ReferenceType
{
    public function key(): string
    {
        return 'regions';
    }

    public function label(): string
    {
        return 'Emirates';
    }

    public function description(): string
    {
        return 'Emirates (regions) of a country. Aliases are other spellings found in address and city columns (e.g. "DXB", "دبي").';
    }

    protected function model(): string
    {
        return Region::class;
    }

    public function fields(): array
    {
        return [
            self::field('name', 'Name', 'translated', true),
            self::field('country_id', 'Country', 'select', true, null, array_values(Country::query()->orderBy('iso2')->get()->map(fn (Country $country): array => ['value' => $country->id, 'label' => $country->name])->values()->all())),
            self::field('slug', 'Slug', 'text', false, 'Made from the English name when empty.'),
            self::field('aliases', 'Other spellings', 'aliases'),
        ];
    }

    public function rules(?Model $item, array $values): array
    {
        return [
            ...self::translatedRules(),
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'slug' => [...self::slugRules()['slug'], Rule::unique('regions', 'slug')->ignore($item?->getKey())],
            ...self::aliasRules(),
        ];
    }

    public function values(Model $item): array
    {
        return ['name' => $item->getTranslations('name'), 'country_id' => $item->country_id, 'slug' => $item->slug, 'aliases' => $item->aliases ?? []];
    }

    public function fill(Model $item, array $values): void
    {
        $item->setTranslations('name', self::translated($values['name'] ?? null));
        $item->country_id = (int) $values['country_id'];
        $item->slug = self::slug($values, $item, Region::query(), 'region');
        $item->aliases = self::aliases($values['aliases'] ?? null);
    }

    public function title(Model $item): string
    {
        return self::name($item);
    }

    public function search(Builder $query, string $term): void
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $query->where(fn (Builder $inner) => $inner->whereRaw('name::text ILIKE ?', [$like])->orWhere('slug', 'ILIKE', $like)->orWhereRaw('aliases::text ILIKE ?', [$like]));
    }

    public function deleteBlocker(Model $item): ?string
    {
        if (Area::query()->where('region_id', $item->id)->exists()) {
            return 'It still has areas. Delete or move them first.';
        }
        if (Contact::query()->where('region_id', $item->id)->exists()) {
            return 'Contacts use this emirate.';
        }

        return null;
    }
}
