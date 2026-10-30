<?php

namespace App\Queries\Audience;

use App\DTOs\AudienceFilters;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

/**
 * Spatie QueryBuilder filter that delegates to AudienceFilterApplier, so the records list and
 * the count queries always filter the same way.
 *
 * @implements Filter<Contact>
 */
class AudienceQueryFilter implements Filter
{
    public function __construct(private AudienceFilterApplier $applier) {}

    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        $this->applier->applyKey($query, $property, AudienceFilters::normalizeValue($property, $value));
    }
}
