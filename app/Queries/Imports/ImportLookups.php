<?php

namespace App\Queries\Imports;

use App\Actions\Normalization\NormalizeArabicTextAction;
use App\Models\Country;
use App\Models\Sector;
use Illuminate\Container\Attributes\Scoped;

/**
 * Country and sector lookups by any spelling (ISO code, English or Arabic name, or a stored alias such
 * as "Emirati"), loaded once per job/request.
 */
#[Scoped]
class ImportLookups
{
    /** @var array<string, int>|null */
    private ?array $countries = null;

    /** @var array<string, int>|null */
    private ?array $countryIdsByIso = null;

    /** @var array<string, int>|null */
    private ?array $sectors = null;

    public function __construct(private NormalizeArabicTextAction $normalize) {}

    public function countryId(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $this->countries ??= $this->loadCountries();
        $key = $this->normalize->handle($value);

        return $this->countries[$key] ?? null;
    }

    public function countryIdByIso(?string $iso): ?int
    {
        if ($iso === null) {
            return null;
        }

        $this->countryIdsByIso ??= Country::query()->pluck('id', 'iso2')->map(fn (mixed $id): int => (int) $id)->all();

        return $this->countryIdsByIso[strtoupper($iso)] ?? null;
    }

    public function sectorId(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if ($this->sectors === null) {
            $this->sectors = [];
            foreach (Sector::query()->get() as $sector) {
                $this->sectors[$this->normalize->handle($sector->slug)] = $sector->id;
                foreach ($sector->getTranslations('name') as $name) {
                    $this->sectors[$this->normalize->handle($name)] = $sector->id;
                }
            }
        }

        return $this->sectors[$this->normalize->handle($value)] ?? null;
    }

    /**
     * @return array<string, int>
     */
    private function loadCountries(): array
    {
        $map = [];
        foreach (Country::query()->get() as $country) {
            $map[$this->normalize->handle($country->iso2)] = $country->id;
            foreach ([...array_values($country->getTranslations('name')), ...$country->aliases] as $name) {
                $map[$this->normalize->handle($name)] ??= $country->id;
            }
        }

        return $map;
    }
}
