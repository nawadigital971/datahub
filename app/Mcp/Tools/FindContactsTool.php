<?php

namespace App\Mcp\Tools;

use App\Actions\Mcp\ResolveAudienceFiltersAction;
use App\Enums\Permission;
use App\Http\Resources\ContactResource;
use App\Mcp\Concerns\ChecksPermissions;
use App\Models\Contact;
use App\Models\User;
use App\Queries\Audience\AudienceLookups;
use App\Queries\Contacts\FindContactsQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('find_contacts')]
#[Description('Look up stored contacts to check or correct them: by name, company, phone or email text ("search"), by the same filters as query_audience_counts, or by "ids". Returns up to 50 contacts with their ids, names, location, sector and flags. Phones and emails are masked unless the account may see them. Use update_contacts to correct fields.')]
#[IsReadOnly]
class FindContactsTool extends Tool
{
    use ChecksPermissions;

    /** @var list<Permission> */
    protected array $permissions = [Permission::ContactsView];

    public function schema(JsonSchema $schema): array
    {
        $list = fn (string $description) => $schema->array()->items($schema->string())->description($description);

        return [
            'search' => $schema->string()->description('Name, company, phone or email text (at least 2 characters).'),
            'ids' => $schema->array()->items($schema->integer())->max(FindContactsQuery::MAX)->description('Contact ids to read.'),
            'source' => $list('Source slugs or names.'),
            'region' => $list('Emirates.'),
            'area' => $list('Areas.'),
            'sector' => $list('Sectors.'),
            'is_junk' => $schema->boolean()->description('Only contacts flagged as junk names.'),
            'limit' => $schema->integer()->min(1)->max(FindContactsQuery::MAX),
        ];
    }

    public function handle(Request $request, ResolveAudienceFiltersAction $resolve, FindContactsQuery $contacts, AudienceLookups $lookups): Response
    {
        if (! $this->allowed($request->user())) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'ids' => ['nullable', 'array', 'max:'.FindContactsQuery::MAX],
            'ids.*' => ['integer'],
            'limit' => ['nullable', 'integer', 'between:1,'.FindContactsQuery::MAX],
            'search' => ['nullable', 'string', 'max:200'],
            'is_junk' => ['nullable', 'boolean'],
        ]);

        $filters = $resolve->handle([
            ...$request->all(),
            'hide_dnc' => false,
            'hide_junk' => ! ($validated['is_junk'] ?? false),
        ]);
        $found = $contacts->handle($filters, array_values(array_map(intval(...), $validated['ids'] ?? [])), (int) ($validated['limit'] ?? 20));
        if (($validated['is_junk'] ?? false) === true) {
            $found = $found->filter(fn (Contact $contact): bool => $contact->is_junk)->values();
        }

        $user = $request->user();
        $unmasked = $user instanceof User && $user->can(Permission::ContactsViewUnmasked->value);

        return Response::json([
            'count' => $found->count(),
            'contacts' => $found->map(fn (Contact $contact): array => [
                'id' => $contact->id,
                'type' => $contact->type->value,
                'name_en' => $contact->name_en,
                'name_ar' => $contact->name_ar,
                'company_name' => $contact->company_name,
                'gender' => $contact->gender?->value,
                'mobile' => $unmasked ? $contact->mobile_e164 : ContactResource::maskPhone($contact->mobile_e164),
                'landline' => $unmasked ? $contact->landline_e164 : ContactResource::maskPhone($contact->landline_e164),
                'email' => $unmasked ? $contact->email : ContactResource::maskEmail($contact->email),
                'region' => ['id' => $contact->region_id, 'name' => $contact->region_id ? $lookups->label('region', $contact->region_id) : null],
                'area' => ['id' => $contact->area_id, 'name' => $contact->area_id ? $lookups->label('area', $contact->area_id) : null],
                'sector' => ['id' => $contact->sector_id, 'name' => $contact->sector_id ? $lookups->label('sector', $contact->sector_id) : null],
                'nationality_country_id' => $contact->nationality_country_id,
                'address' => $contact->address_raw,
                'sources' => array_map(fn (int $id): ?string => $lookups->label('source', $id), array_map(intval(...), $contact->source_ids)),
                'last_seen_year' => $contact->last_seen_year,
                'is_junk' => $contact->is_junk,
                'do_not_contact' => $contact->do_not_contact,
            ])->all(),
        ]);
    }
}
