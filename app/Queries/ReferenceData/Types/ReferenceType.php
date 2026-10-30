<?php

namespace App\Queries\ReferenceData\Types;

use App\Enums\Permission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One editable list of reference data (emirates, areas, cleaning rules, do-not-contact…). The same
 * description drives the dashboard editor and the MCP tools, so a new list only needs a new type.
 *
 * @phpstan-type Option array{value: int|string, label: string}
 * @phpstan-type Field array{name: string, label: string, kind: string, required: bool, hint?: string, options?: list<Option>}
 *
 * @template TModel of Model
 */
abstract class ReferenceType
{
    abstract public function key(): string;

    abstract public function label(): string;

    abstract public function description(): string;

    /**
     * @return class-string<TModel>
     */
    abstract protected function model(): string;

    /**
     * Form fields. Kinds: text, textarea, number, translated (one value per locale), aliases (list of
     * other spellings), select (one of options), map (column header => import target).
     *
     * @return list<Field>
     */
    abstract public function fields(): array;

    /**
     * Validation rules for the values, keyed by field name.
     *
     * @param  TModel|null  $item
     * @param  array<string, mixed>  $values  the values being checked
     * @return array<string, mixed>
     */
    abstract public function rules(?Model $item, array $values): array;

    /**
     * The values of one item, in the same shape the form sends back.
     *
     * @param  TModel  $item
     * @return array<string, mixed>
     */
    abstract public function values(Model $item): array;

    /**
     * Copy validated values onto the item (it is saved afterwards).
     *
     * @param  TModel  $item
     * @param  array<string, mixed>  $values
     */
    abstract public function fill(Model $item, array $values): void;

    /**
     * A short name for the item, used in messages.
     *
     * @param  TModel  $item
     */
    abstract public function title(Model $item): string;

    public function permission(): Permission
    {
        return Permission::SettingsUpdate;
    }

    /**
     * @return Builder<TModel>
     */
    public function query(): Builder
    {
        return $this->model()::query();
    }

    /**
     * @param  Builder<TModel>  $query
     */
    public function search(Builder $query, string $term): void
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $query->where(fn (Builder $inner) => $inner->whereRaw('name::text ILIKE ?', [$like])->orWhere('slug', 'ILIKE', $like));
    }

    /**
     * @return list<string>
     */
    public function sorts(): array
    {
        return ['id'];
    }

    public function defaultSort(): string
    {
        return 'id';
    }

    /**
     * @return TModel|null
     */
    public function find(int $id): ?Model
    {
        return $this->query()->find($id);
    }

    /**
     * @return TModel
     */
    public function newItem(): Model
    {
        $class = $this->model();

        return new $class;
    }

    /**
     * Why the item cannot be deleted (it is still in use), or null when it can.
     *
     * @param  TModel  $item
     */
    public function deleteBlocker(Model $item): ?string
    {
        return null;
    }

    /**
     * Runs after the item is saved, in the same transaction.
     *
     * @param  TModel  $item
     */
    public function saved(Model $item, ?int $userId): void {}

    /**
     * @param  TModel  $item
     * @return array{id: int, title: string, values: array<string, mixed>}
     */
    public function present(Model $item): array
    {
        return ['id' => (int) $item->getKey(), 'title' => $this->title($item), 'values' => $this->values($item)];
    }

    /**
     * @return array{key: string, label: string, description: string, permission: string, fields: list<Field>}
     */
    public function schema(): array
    {
        return [
            'key' => $this->key(),
            'label' => $this->label(),
            'description' => $this->description(),
            'permission' => $this->permission()->value,
            'fields' => $this->fields(),
        ];
    }

    /* ---------- shared field helpers ---------- */

    /**
     * @param  list<Option>  $options
     * @return Field
     */
    protected static function field(string $name, string $label, string $kind = 'text', bool $required = false, ?string $hint = null, ?array $options = null): array
    {
        $field = ['name' => $name, 'label' => $label, 'kind' => $kind, 'required' => $required];
        if ($hint !== null) {
            $field['hint'] = $hint;
        }
        if ($options !== null) {
            $field['options'] = $options;
        }

        return $field;
    }

    /**
     * @return array<string, list<string>>
     */
    protected static function translatedRules(string $name = 'name', int $max = 120): array
    {
        return [
            $name => ['required', 'array:'.implode(',', self::locales())],
            $name.'.'.config('app.fallback_locale') => ['required', 'string', 'max:'.$max],
            $name.'.*' => ['nullable', 'string', 'max:'.$max],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    protected static function aliasRules(): array
    {
        return [
            'aliases' => ['nullable', 'array', 'max:300'],
            'aliases.*' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    protected static function slugRules(): array
    {
        return ['slug' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/']];
    }

    /**
     * @return array<string, string>
     */
    protected static function translated(mixed $value): array
    {
        $out = [];
        foreach (is_array($value) ? $value : [] as $locale => $text) {
            if (is_string($locale) && is_scalar($text) && trim((string) $text) !== '') {
                $out[$locale] = trim((string) $text);
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    protected static function aliases(mixed $value): array
    {
        $out = [];
        foreach (is_array($value) ? $value : [] as $alias) {
            $alias = is_scalar($alias) ? trim((string) $alias) : '';
            if ($alias !== '' && ! in_array(mb_strtolower($alias), array_map(mb_strtolower(...), $out), true)) {
                $out[] = $alias;
            }
        }

        return $out;
    }

    /**
     * The given slug, or one made from the English name that is not used yet.
     *
     * @param  array<string, mixed>  $values
     * @param  Builder<TModel>  $scope
     */
    protected static function slug(array $values, Model $item, Builder $scope, string $fallback): string
    {
        $given = is_string($values['slug'] ?? null) ? trim($values['slug']) : '';
        if ($given !== '') {
            return $given;
        }
        $current = $item->getAttribute('slug');
        if (is_string($current) && $current !== '') {
            return $current;
        }

        $name = self::translated($values['name'] ?? null);
        $base = Str::slug($name[config('app.fallback_locale')] ?? $fallback) ?: $fallback;
        $slug = $base;
        for ($n = 2; (clone $scope)->where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    /**
     * @return list<string>
     */
    protected static function locales(): array
    {
        return array_values(array_map(strval(...), (array) config('app.locales')));
    }

    protected static function name(Model $item): string
    {
        $name = $item->getAttribute('name');

        return is_string($name) ? $name : '';
    }
}
