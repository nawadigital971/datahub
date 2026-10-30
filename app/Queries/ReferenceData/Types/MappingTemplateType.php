<?php

namespace App\Queries\ReferenceData\Types;

use App\Enums\ImportTarget;
use App\Enums\MappingLayout;
use App\Models\MappingTemplate;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * @extends ReferenceType<MappingTemplate>
 */
class MappingTemplateType extends ReferenceType
{
    public function key(): string
    {
        return 'mapping_templates';
    }

    public function label(): string
    {
        return 'Mapping templates';
    }

    public function description(): string
    {
        return 'Saved column mappings. When a file has the same headers, its columns are mapped automatically. Each entry maps a column header to a target (phone, landline, email, name, name_en, name_ar, company_name, source, gender, nationality, address_raw, region, sector, seen_year, notes, type, ignore, or extra.<key>).';
    }

    protected function model(): string
    {
        return MappingTemplate::class;
    }

    public function fields(): array
    {
        return [
            self::field('name', 'Name', 'text', true),
            self::field('layout', 'Layout', 'select', true, null, array_map(fn (MappingLayout $layout): array => ['value' => $layout->value, 'label' => $layout->label()], MappingLayout::cases())),
            self::field('column_map', 'Columns', 'map', true, 'Column header in the file → where it goes.', array_map(fn (ImportTarget $target): array => ['value' => $target->value, 'label' => $target->label()], ImportTarget::cases())),
        ];
    }

    public function rules(?Model $item, array $values): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('mapping_templates', 'name')->ignore($item?->getKey())],
            'layout' => ['required', 'integer', Rule::enum(MappingLayout::class)],
            'column_map' => ['required', 'array', 'min:1', 'max:300'],
            'column_map.*.header' => ['required', 'string', 'max:200', 'distinct'],
            'column_map.*.target' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! ImportTarget::isValid($value)) {
                    $fail('Choose a valid destination for every column.');
                }
            }],
        ];
    }

    public function values(Model $item): array
    {
        $pairs = [];
        foreach ($item->column_map as $header => $target) {
            $pairs[] = ['header' => (string) $header, 'target' => $target];
        }

        return ['name' => $item->name, 'layout' => $item->layout->value, 'column_map' => $pairs];
    }

    public function fill(Model $item, array $values): void
    {
        $map = [];
        foreach (is_array($values['column_map'] ?? null) ? $values['column_map'] : [] as $pair) {
            if (is_array($pair) && is_scalar($pair['header'] ?? null) && is_string($pair['target'] ?? null)) {
                $map[trim((string) $pair['header'])] = $pair['target'];
            }
        }

        $item->name = trim((string) $values['name']);
        $item->layout = MappingLayout::from((int) $values['layout']);
        $item->column_map = $map;
    }

    public function title(Model $item): string
    {
        return $item->name;
    }

    public function search(Builder $query, string $term): void
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $query->where(fn (Builder $inner) => $inner->where('name', 'ILIKE', $like)->orWhereRaw('column_map::text ILIKE ?', [$like]));
    }

    public function sorts(): array
    {
        return ['id', 'name'];
    }

    public function defaultSort(): string
    {
        return 'name';
    }
}
