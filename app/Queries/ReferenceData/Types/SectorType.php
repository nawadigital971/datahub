<?php

namespace App\Queries\ReferenceData\Types;

use App\Models\Contact;
use App\Models\Sector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * @extends ReferenceType<Sector>
 */
class SectorType extends ReferenceType
{
    public function key(): string
    {
        return 'sectors';
    }

    public function label(): string
    {
        return 'Sectors';
    }

    public function description(): string
    {
        return 'Business sectors (e.g. Real estate). Imports match the sector column against the names and the slug.';
    }

    protected function model(): string
    {
        return Sector::class;
    }

    public function fields(): array
    {
        return [
            self::field('name', 'Name', 'translated', true),
            self::field('slug', 'Slug', 'text', false, 'Made from the English name when empty.'),
        ];
    }

    public function rules(?Model $item, array $values): array
    {
        return [
            ...self::translatedRules(),
            'slug' => [...self::slugRules()['slug'], Rule::unique('sectors', 'slug')->ignore($item?->getKey())],
        ];
    }

    public function values(Model $item): array
    {
        return ['name' => $item->getTranslations('name'), 'slug' => $item->slug];
    }

    public function fill(Model $item, array $values): void
    {
        $item->setTranslations('name', self::translated($values['name'] ?? null));
        $item->slug = self::slug($values, $item, Sector::query(), 'sector');
    }

    public function title(Model $item): string
    {
        return self::name($item);
    }

    public function deleteBlocker(Model $item): ?string
    {
        return Contact::query()->where('sector_id', $item->id)->exists() ? 'Contacts use this sector.' : null;
    }
}
