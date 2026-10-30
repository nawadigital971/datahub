<?php

namespace App\Queries\Contacts;

use App\DTOs\AudienceFilters;
use App\Models\Contact;
use App\Queries\Audience\AudienceFilterApplier;
use Illuminate\Database\Eloquent\Collection;

class FindContactsQuery
{
    public const int MAX = 50;

    public function __construct(private AudienceFilterApplier $filters) {}

    /**
     * The first contacts matching the Audience filters, newest first (no COUNT over the table).
     *
     * @param  list<int>  $ids  look these contacts up directly instead
     * @return Collection<int, Contact>
     */
    public function handle(AudienceFilters $filters, array $ids = [], int $limit = self::MAX): Collection
    {
        $query = Contact::query();

        if ($ids !== []) {
            $query->whereKey($ids);
        } else {
            $this->filters->apply($query, $filters);
        }

        return $query->orderByDesc('id')->limit(min($limit, self::MAX))->get();
    }
}
