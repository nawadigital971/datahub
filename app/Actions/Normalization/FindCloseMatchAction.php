<?php

namespace App\Actions\Normalization;

/**
 * Catches small typos ("Dubay" → Dubai, "Al Barsah" → Al Barsha) so they match an existing item instead of
 * becoming a new one: at most 1 different letter for words of 5+ letters, 2 for 9+. Ties match nothing.
 */
class FindCloseMatchAction
{
    /**
     * @template TId
     *
     * @param  string  $needle  already normalized
     * @param  array<string, TId>  $candidates  normalized spelling => id
     * @return TId|null
     */
    public function handle(string $needle, array $candidates): mixed
    {
        $length = mb_strlen($needle);
        if ($length < 5) {
            return null;
        }
        $allowed = $length >= 9 ? 2 : 1;

        $best = null;
        $bestDistance = $allowed + 1;
        $tie = false;
        foreach ($candidates as $spelling => $id) {
            if (abs(mb_strlen($spelling) - $length) > $allowed) {
                continue;
            }
            $distance = $this->distance($needle, $spelling, $allowed);
            if ($distance < $bestDistance) {
                [$best, $bestDistance, $tie] = [$id, $distance, false];
            } elseif ($distance === $bestDistance && $id !== $best) {
                $tie = true;
            }
        }

        return $tie || $bestDistance > $allowed ? null : $best;
    }

    /**
     * Levenshtein distance on characters (works for Arabic), stopping early once it exceeds $limit.
     */
    private function distance(string $a, string $b, int $limit): int
    {
        $a = mb_str_split($a);
        $b = mb_str_split($b);
        $previous = range(0, count($b));

        foreach ($a as $i => $charA) {
            $current = [$i + 1];
            foreach ($b as $j => $charB) {
                $current[] = min($previous[$j + 1] + 1, $current[$j] + 1, $previous[$j] + ($charA === $charB ? 0 : 1));
            }
            if (min($current) > $limit) {
                return $limit + 1;
            }
            $previous = $current;
        }

        return $previous[count($b)];
    }
}
