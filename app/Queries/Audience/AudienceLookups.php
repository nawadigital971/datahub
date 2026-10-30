<?php

namespace App\Queries\Audience;

use App\Enums\CallOutcome;
use App\Enums\ContactType;
use App\Models\Area;
use App\Models\Country;
use App\Models\Region;
use App\Models\Sector;
use App\Models\Source;
use Illuminate\Container\Attributes\Scoped;

/**
 * Labels for every facet value, in the current locale. Cached across requests and memoized per request.
 *
 * @phpstan-type Labels array<string, array<int, string>>
 */
#[Scoped]
class AudienceLookups
{
    /** Labels for value 0 ("unknown / none") per facet, as in the prototype. */
    public const array UNKNOWN_LABELS = [
        'country' => 'Unknown',
        'region' => 'Outside UAE / unknown',
        'area' => 'Unknown area',
        'sector' => 'No sector',
        'gender' => 'Unknown',
        'call_outcome' => 'Never called',
    ];

    /** @var Labels|null */
    private ?array $labels = null;

    public function __construct(private AudienceCache $cache) {}

    /**
     * @return Labels
     */
    public function all(): array
    {
        return $this->labels ??= $this->cache->remember('lookups', app()->getLocale(), fn (): array => $this->load(), 3600);
    }

    public function label(string $dimension, ?int $id): ?string
    {
        return $this->all()[$dimension][$id ?? 0] ?? null;
    }

    /**
     * @return Labels
     */
    private function load(): array
    {
        $names = fn (iterable $models): array => collect($models)
            ->mapWithKeys(fn (Source|Country|Region|Area|Sector $model): array => [$model->id => $model->name])
            ->all();

        return [
            'source' => $names(Source::query()->orderBy('id')->get()),
            'type' => [
                ContactType::Person->value => 'Person (B2C)',
                ContactType::Business->value => 'Business (B2B)',
            ],
            'country' => [0 => self::UNKNOWN_LABELS['country']] + $names(Country::query()->orderBy('id')->get()),
            'country_iso' => Country::query()->pluck('iso2', 'id')->map(fn (mixed $iso): string => (string) $iso)->all(),
            'region' => [0 => self::UNKNOWN_LABELS['region']] + $names(Region::query()->orderBy('id')->get()),
            'area' => [0 => self::UNKNOWN_LABELS['area']] + $names(Area::query()->orderBy('id')->get()),
            'sector' => [0 => self::UNKNOWN_LABELS['sector']] + $names(Sector::query()->orderBy('id')->get()),
            'gender' => [0 => self::UNKNOWN_LABELS['gender'], 1 => 'Male', 2 => 'Female'],
            'call_outcome' => [0 => self::UNKNOWN_LABELS['call_outcome']] + collect(CallOutcome::cases())
                ->mapWithKeys(fn (CallOutcome $outcome): array => [$outcome->value => $outcome->label()])
                ->all(),
        ];
    }
}
