<?php

namespace App\Queries\Imports;

use App\Enums\IssueCode;
use App\Enums\MatchStatus;
use App\Enums\RowSeverity;
use App\Enums\StagedRowAction;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use ValueError;

/**
 * Preview filters (issue code, match status, action, severity), shared by the table and by bulk actions
 * so "apply to all matching rows" means exactly the rows the reviewer sees.
 * Values are comma-separated keys, e.g. filter[issue]=email_typo,invalid_phone.
 */
class StagedRowFilters
{
    public const array KEYS = ['issue', 'match', 'action', 'severity'];

    /**
     * @template TQuery of QueryBuilder|EloquentBuilder<\App\Models\StagedRow>
     *
     * @param  TQuery  $query
     * @param  array<string, mixed>  $filters
     * @return TQuery
     */
    public function apply(QueryBuilder|EloquentBuilder $query, array $filters): QueryBuilder|EloquentBuilder
    {
        foreach (self::KEYS as $key) {
            $values = $this->values($filters[$key] ?? null);
            if ($values !== []) {
                $this->applyKey($query, $key, $values);
            }
        }

        return $query;
    }

    /**
     * @param  QueryBuilder|EloquentBuilder<*>  $query
     * @param  list<string>  $values
     */
    public function applyKey(QueryBuilder|EloquentBuilder $query, string $key, array $values): void
    {
        try {
            match ($key) {
                'issue' => $query->whereRaw('issue_codes && ?::text[]', ['{'.implode(',', array_map(fn (string $code): string => IssueCode::from($code)->value, $values)).'}']),
                'match' => $query->whereIn('match_status', array_map(fn (string $value): int => MatchStatus::fromKey($value)->value, $values)),
                'action' => $query->whereIn('action', array_map(fn (string $value): int => StagedRowAction::fromKey($value)->value, $values)),
                'severity' => $query->whereIn('severity', array_map(fn (string $value): int => RowSeverity::fromKey($value)->value, $values)),
                default => null,
            };
        } catch (ValueError) {
            $query->whereRaw('false');
        }
    }

    /**
     * @return list<string>
     */
    public function values(mixed $value): array
    {
        $parts = is_array($value) ? $value : explode(',', is_string($value) ? $value : '');

        return array_values(array_filter(array_map(fn (mixed $part): string => is_scalar($part) ? trim((string) $part) : '', $parts)));
    }
}
