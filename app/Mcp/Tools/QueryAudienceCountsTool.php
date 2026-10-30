<?php

namespace App\Mcp\Tools;

use App\Actions\Mcp\ResolveAudienceFiltersAction;
use App\Enums\Permission;
use App\Mcp\Concerns\ChecksPermissions;
use App\Queries\Audience\AudienceFilterDescriber;
use App\Queries\Audience\AudienceLookups;
use App\Queries\Audience\AudienceSummaryQuery;
use App\Queries\FacetCountsQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('query_audience_counts')]
#[Description('Count an audience with the same filters as the Audience page and see how many each channel (email, WhatsApp, SMS, Meta, Google, call centre) can reach. Returns counts only, never contact details. Do-not-contact people are hidden unless hide_dnc is false, and never counted for channels.')]
#[IsReadOnly]
class QueryAudienceCountsTool extends Tool
{
    use ChecksPermissions;

    public const int BREAKDOWN_LIMIT = 10;

    /** @var list<Permission> */
    protected array $permissions = [Permission::ContactsView];

    public function schema(JsonSchema $schema): array
    {
        $list = fn (string $description) => $schema->array()->items($schema->string())->description($description);

        return [
            'search' => $schema->string()->description('Name, company, phone or email text.'),
            'source' => $list('Source slugs (see get_reference_data).'),
            'region' => $list('Emirates, e.g. ["Dubai", "Sharjah"].'),
            'area' => $list('Areas / districts, e.g. ["Deira", "International City"].'),
            'country' => $list('Country of the phone number as ISO codes, e.g. ["AE", "EG"].'),
            'sector' => $list('Business sector names or slugs.'),
            'type' => $list('"person" and/or "business".'),
            'gender' => $list('"male", "female" and/or "unknown".'),
            'call_outcome' => $list('Last call outcome, e.g. "Not interested", "Never called".'),
            'year_from' => $schema->integer()->description('Last seen in this year or later.'),
            'year_to' => $schema->integer()->description('Last seen in this year or earlier.'),
            'hide_dnc' => $schema->boolean()->description('Hide do-not-contact people (default true).'),
            'hide_junk' => $schema->boolean(),
            'has_mobile' => $schema->boolean(),
            'has_email' => $schema->boolean(),
            'has_landline' => $schema->boolean(),
            'has_arabic_name' => $schema->boolean(),
            'include_breakdown' => $schema->boolean()->description('Also return the top sources, emirates, countries and areas of this audience.'),
        ];
    }

    public function handle(
        Request $request,
        ResolveAudienceFiltersAction $resolve,
        AudienceSummaryQuery $summary,
        FacetCountsQuery $facets,
        AudienceFilterDescriber $describe,
        AudienceLookups $lookups,
    ): Response {
        if (! $this->allowed($request->user())) {
            return $this->forbidden();
        }

        $filters = $resolve->handle($request->all());
        $counts = $summary->handle($filters);

        $result = [
            'audience' => $describe->handle($filters),
            'total' => $counts['total'],
            'with_mobile' => $counts['with_mobile'],
            'uae_mobile' => $counts['uae_mobile'],
            'usable_email' => $counts['usable_email'],
            'with_landline' => $counts['with_landline'],
            'channels' => $counts['channels'],
        ];

        if ($request->boolean('include_breakdown')) {
            $charts = $facets->handle($filters)['charts'];
            foreach (['source', 'region', 'country', 'area'] as $dimension) {
                $top = array_filter($charts[$dimension], fn (int $count, int $id): bool => $id !== 0, ARRAY_FILTER_USE_BOTH);
                arsort($top);
                $result['breakdown'][$dimension] = array_map(
                    fn (int $id, int $count): array => ['value' => $lookups->label($dimension, $id) ?? 'Unknown', 'count' => $count],
                    array_keys(array_slice($top, 0, self::BREAKDOWN_LIMIT, true)),
                    array_slice($top, 0, self::BREAKDOWN_LIMIT, true),
                );
            }
        }

        return Response::json($result);
    }
}
