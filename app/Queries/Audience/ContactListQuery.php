<?php

namespace App\Queries\Audience;

use App\DTOs\AudienceFilters;
use App\Models\Contact;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The Audience records table: filtered, newest first, cursor-paginated (never COUNT(*)).
 */
class ContactListQuery
{
    public const int PER_PAGE = 25;

    /** Only the columns the table shows (search_text and extra stay in the database). */
    private const array COLUMNS = [
        'id', 'type', 'name_en', 'name_ar', 'other_names', 'company_name', 'name_en_verified', 'gender', 'country_id',
        'region_id', 'area_id', 'sector_id', 'mobile_e164', 'landline_e164', 'email', 'email_status',
        'has_mobile', 'has_uae_mobile', 'has_email', 'source_ids', 'last_seen_year', 'do_not_contact', 'is_junk',
    ];

    public function __construct(private AudienceQueryFilter $filter) {}

    /**
     * @return CursorPaginator<int, Contact>
     */
    public function handle(Request $request): CursorPaginator
    {
        $filters = array_map(function (string $key): AllowedFilter {
            $filter = AllowedFilter::custom($key, $this->filter);

            return array_key_exists($key, AudienceFilters::TOGGLES) && AudienceFilters::TOGGLES[$key]
                ? $filter->default(true)
                : $filter;
        }, AudienceFilters::KEYS);

        return QueryBuilder::for(Contact::query()->select(self::COLUMNS), $request)
            ->allowedFilters(...$filters)
            ->allowedSorts('id')
            ->defaultSort('-id')
            ->allowedIncludes()
            ->cursorPaginate(self::PER_PAGE)
            ->withQueryString();
    }
}
