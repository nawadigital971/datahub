<?php

namespace App\Queries\ReferenceData\Types;

use App\Models\Area;
use App\Models\Contact;
use App\Models\Region;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * @extends ReferenceType<Area>
 */
class AreaType extends ReferenceType
{
    public function key(): string
    {
        return 'areas';
    }

    public function label(): string
    {
        return 'Areas';
    }

    public function description(): string
    {
        return 'Areas inside an emirate (e.g. Al Barsha). Imports match addresses against the names and aliases.';
    }

    protected function model(): string
    {
        return Area::class;
    }

    public function fields(): array
    {
        return [
            self::field('name', 'Name', 'translated', true),
            self::field('region_id', 'Emirate', 'select', true, null, array_values(Region::query()->orderBy('id')->get()->map(fn (Region $region): array => ['value' => $region->id, 'label' => $region->name])->values()->all())),
            self::field('slug', 'Slug', 'text', false, 'Made from the English name when empty.'),
            self::field('aliases', 'Other spellings', 'aliases', false, 'Spellings found in files, in English or Arabic.'),
        ];
    }

    public function rules(?Model $item, array $values): array
    {
        $regionId = $values['region_id'] ?? null;

        return [
            ...self::translatedRules(),
            'region_id' => ['required', 'integer', 'exists:regions,id'],
            'slug' => [...self::slugRules()['slug'], Rule::unique('areas', 'slug')->where('region_id', is_numeric($regionId) ? (int) $regionId : 0)->ignore($item?->getKey())],
            ...self::aliasRules(),
        ];
    }

    public function values(Model $item): array
    {
        return ['name' => $item->getTranslations('name'), 'region_id' => $item->region_id, 'slug' => $item->slug, 'aliases' => $item->aliases ?? []];
    }

    public function fill(Model $item, array $values): void
    {
        $item->setTranslations('name', self::translated($values['name'] ?? null));
        $item->region_id = (int) $values['region_id'];
        $item->slug = self::slug($values, $item, Area::query()->where('region_id', $item->region_id), 'area');
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

    public function sorts(): array
    {
        return ['id', 'slug', 'region_id'];
    }

    public function defaultSort(): string
    {
        return 'region_id';
    }

    public function deleteBlocker(Model $item): ?string
    {
        return Contact::query()->where('area_id', $item->id)->exists() ? 'Contacts use this area.' : null;
    }
}
