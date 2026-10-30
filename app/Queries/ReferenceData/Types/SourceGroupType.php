<?php

namespace App\Queries\ReferenceData\Types;

use App\Models\Source;
use App\Models\SourceGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * @extends ReferenceType<SourceGroup>
 */
class SourceGroupType extends ReferenceType
{
    public function key(): string
    {
        return 'source_groups';
    }

    public function label(): string
    {
        return 'Source groups';
    }

    public function description(): string
    {
        return 'Groups that sources belong to (e.g. Events, Directories).';
    }

    protected function model(): string
    {
        return SourceGroup::class;
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
            'slug' => [...self::slugRules()['slug'], Rule::unique('source_groups', 'slug')->ignore($item?->getKey())],
        ];
    }

    public function values(Model $item): array
    {
        return ['name' => $item->getTranslations('name'), 'slug' => $item->slug];
    }

    public function fill(Model $item, array $values): void
    {
        $item->setTranslations('name', self::translated($values['name'] ?? null));
        $item->slug = self::slug($values, $item, SourceGroup::query(), 'group');
    }

    public function title(Model $item): string
    {
        return self::name($item);
    }

    public function deleteBlocker(Model $item): ?string
    {
        return Source::query()->where('source_group_id', $item->id)->exists() ? 'Sources use this group. Move them to another group first.' : null;
    }
}
