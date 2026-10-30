<?php

namespace App\Actions\Mcp;

use App\Actions\Normalization\NormalizeArabicTextAction;
use App\DTOs\AudienceFilters;
use App\Enums\CallOutcome;
use App\Enums\ContactType;
use App\Models\Area;
use App\Models\Country;
use App\Models\Region;
use App\Models\Sector;
use App\Models\Source;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Lets Claude describe an audience with readable values (source slugs, emirate names, ISO country codes…)
 * instead of database ids. Every value must resolve, otherwise the tool explains which one is unknown.
 */
class ResolveAudienceFiltersAction
{
    public function __construct(private NormalizeArabicTextAction $normalize) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input): AudienceFilters
    {
        $filters = [];

        foreach (['search', 'year_from', 'year_to', ...array_keys(AudienceFilters::TOGGLES)] as $key) {
            if (array_key_exists($key, $input)) {
                $filters[$key] = $input[$key];
            }
        }

        foreach (AudienceFilters::FACETS as $facet) {
            $values = $input[$facet] ?? [];
            $values = is_array($values) ? $values : [$values];
            if ($values !== []) {
                $filters[$facet] = implode(',', array_map(fn (mixed $value): int => $this->resolve($facet, $value), $values));
            }
        }

        return AudienceFilters::fromArray($filters);
    }

    private function resolve(string $facet, mixed $value): int
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }

        $key = $this->normalize->handle(is_scalar($value) ? (string) $value : '');

        $id = match ($facet) {
            'source' => $this->find(Source::query()->get(), $key),
            'region' => $this->find(Region::query()->get(), $key),
            'area' => $this->find(Area::query()->get(), $key),
            'sector' => $this->find(Sector::query()->get(), $key),
            'country' => Country::query()->where('iso2', strtoupper($key))->value('id') ?? $this->find(Country::query()->get(), $key),
            'type' => ['person' => ContactType::Person->value, 'business' => ContactType::Business->value][$key] ?? null,
            'gender' => ['male' => 1, 'm' => 1, 'female' => 2, 'f' => 2, 'unknown' => 0][$key] ?? null,
            'call_outcome' => $key === 'never called' ? 0 : collect(CallOutcome::cases())->first(fn (CallOutcome $o): bool => $this->normalize->handle($o->label()) === $key || strtolower($o->name) === str_replace(' ', '', $key))?->value,
            default => null,
        };

        if ($id === null) {
            throw ValidationException::withMessages([$facet => "Unknown {$facet} “{$key}”. Call get_reference_data for valid values."]);
        }

        return (int) $id;
    }

    /**
     * Match a slug or any translated name.
     *
     * @param  iterable<Model>  $models
     */
    private function find(iterable $models, string $key): ?int
    {
        foreach ($models as $model) {
            $names = method_exists($model, 'getTranslations') ? array_values($model->getTranslations('name')) : [];
            $candidates = [...$names, (string) ($model->getAttribute('slug') ?? '')];
            foreach ($candidates as $candidate) {
                if ($candidate !== '' && $this->normalize->handle(str_replace('-', ' ', $candidate)) === str_replace('-', ' ', $key)) {
                    return (int) $model->getKey();
                }
            }
        }

        return null;
    }
}
