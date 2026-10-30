<?php

namespace App\Queries;

use App\DTOs\AudienceFilters;
use App\Enums\ExportChannel;
use App\Queries\Audience\AudienceFilterApplier;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The contacts an export writes: the audience filters, the channel's reachability rule,
 * and never anyone on the do-not-contact list (whatever the Audience toggle showed).
 */
class ExportRowsQuery
{
    public const array COLUMNS = [
        'id', 'name_en', 'name_ar', 'company_name', 'mobile_e164', 'landline_e164', 'email',
        'region_id', 'area_id', 'country_id', 'source_ids', 'last_call_outcome',
    ];

    public function __construct(private AudienceFilterApplier $applier) {}

    public function handle(AudienceFilters $filters, ExportChannel $channel): Builder
    {
        $query = DB::table('contacts')->select(self::COLUMNS);

        return $this->applier->apply($query, $filters)
            ->where('do_not_contact', false)
            ->whereRaw($channel->contactCondition());
    }
}
