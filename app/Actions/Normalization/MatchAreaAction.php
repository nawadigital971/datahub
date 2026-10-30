<?php

namespace App\Actions\Normalization;

use App\DTOs\Normalization\AreaMatch;
use App\Models\Area;
use App\Models\Region;
use Illuminate\Container\Attributes\Scoped;

/**
 * Finds the emirate and district in free-text addresses ("near Lulu, International City", "ديرة - دبي")
 * using every Arabic and English spelling in the areas dictionary. Longest spelling wins;
 * areas inside an already known emirate are preferred.
 */
#[Scoped]
class MatchAreaAction
{
    /** @var list<array{alias: string, region_id: int}>|null */
    private ?array $regionAliases = null;

    /** @var list<array{alias: string, region_id: int, area_id: int}>|null */
    private ?array $areaAliases = null;

    public function __construct(private NormalizeArabicTextAction $normalize, private FindCloseMatchAction $close) {}

    public function handle(?string $text, ?int $knownRegionId = null): AreaMatch
    {
        $haystack = $this->prepare($text);
        if ($haystack === '  ') {
            return new AreaMatch($knownRegionId, null);
        }

        $regionId = $knownRegionId ?? $this->findRegion($haystack);
        $areaMatches = array_values(array_filter(
            $this->areaAliases(),
            fn (array $entry): bool => str_contains($haystack, ' '.$entry['alias'].' '),
        ));

        if ($regionId !== null) {
            $inRegion = array_values(array_filter($areaMatches, fn (array $entry): bool => $entry['region_id'] === $regionId));
            $areaMatches = $inRegion !== [] ? $inRegion : ($knownRegionId !== null ? [] : $areaMatches);
        }

        $area = $areaMatches[0] ?? null;

        return new AreaMatch($regionId ?? $area['region_id'] ?? null, $area['area_id'] ?? null);
    }

    /**
     * The emirate named in a short value such as an "Emirate" column.
     */
    public function region(?string $text): ?int
    {
        $haystack = $this->prepare($text);

        return $haystack === '  ' ? null : $this->findRegion($haystack);
    }

    /**
     * A short value that is a misspelling of a known area (in the given emirate) or emirate.
     */
    public function closest(?string $text, ?int $regionId = null): AreaMatch
    {
        $needle = trim($this->prepare($text));
        if ($needle === '') {
            return new AreaMatch($regionId, null);
        }

        $areas = [];
        foreach ($this->areaAliases() as $entry) {
            if ($regionId === null || $entry['region_id'] === $regionId) {
                $areas[$entry['alias']] ??= $entry['area_id'];
            }
        }
        $areaId = $this->close->handle($needle, $areas);
        if ($areaId !== null) {
            $area = collect($this->areaAliases())->firstWhere('area_id', $areaId);

            return new AreaMatch($area['region_id'] ?? $regionId, $areaId);
        }

        if ($regionId === null) {
            $regions = [];
            foreach ($this->regionAliases() as $entry) {
                $regions[$entry['alias']] ??= $entry['region_id'];
            }

            return new AreaMatch($this->close->handle($needle, $regions), null);
        }

        return new AreaMatch($regionId, null);
    }

    private function findRegion(string $haystack): ?int
    {
        foreach ($this->regionAliases() as $entry) {
            if (str_contains($haystack, ' '.$entry['alias'].' ')) {
                return $entry['region_id'];
            }
        }

        return null;
    }

    /**
     * Normalized text with punctuation turned into spaces and padded, so aliases match whole words.
     */
    private function prepare(?string $text): string
    {
        $normalized = $this->normalize->handle($text);

        return ' '.trim((string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized)).' ';
    }

    /**
     * @return list<array{alias: string, region_id: int}>
     */
    private function regionAliases(): array
    {
        if ($this->regionAliases !== null) {
            return $this->regionAliases;
        }

        $entries = [];
        foreach (Region::query()->get() as $region) {
            $spellings = [...array_values($region->getTranslations('name')), ...$region->aliases];
            foreach ($spellings as $spelling) {
                $entries[] = ['alias' => trim($this->prepare($spelling)), 'region_id' => $region->id];
            }
        }

        return $this->regionAliases = $this->longestFirst($entries);
    }

    /**
     * @return list<array{alias: string, region_id: int, area_id: int}>
     */
    private function areaAliases(): array
    {
        if ($this->areaAliases !== null) {
            return $this->areaAliases;
        }

        $entries = [];
        foreach (Area::query()->get() as $area) {
            $spellings = [...array_values($area->getTranslations('name')), ...$area->aliases];
            foreach (array_unique($spellings) as $spelling) {
                $alias = trim($this->prepare($spelling));
                if (mb_strlen($alias) >= 3) {
                    $entries[] = ['alias' => $alias, 'region_id' => $area->region_id, 'area_id' => $area->id];
                }
            }
        }

        return $this->areaAliases = $this->longestFirst($entries);
    }

    /**
     * @template TEntry of array{alias: string}
     *
     * @param  list<TEntry>  $entries
     * @return list<TEntry>
     */
    private function longestFirst(array $entries): array
    {
        usort($entries, fn (array $a, array $b): int => mb_strlen($b['alias']) <=> mb_strlen($a['alias']));

        return $entries;
    }
}
