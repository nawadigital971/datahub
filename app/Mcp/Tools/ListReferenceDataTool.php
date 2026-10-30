<?php

namespace App\Mcp\Tools;

use App\Enums\Permission;
use App\Mcp\Concerns\ChecksPermissions;
use App\Mcp\Concerns\UsesReferenceTypes;
use App\Queries\ReferenceData\ReferenceItemListQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request as HttpRequest;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_reference_data')]
#[Description('Read the stored reference lists the import cleaning uses: regions (emirates), areas, countries, sectors, source_groups, cleaning_rules (call codes, email typo fixes, junk names, first names, landline prefixes, column header hints, blocked file names), mapping_templates and do_not_contact. Without "type" it returns every list with its fields and what they mean. With "type" it returns the items (50 per page) with their ids and values, ready to change with save_reference_data.')]
#[IsReadOnly]
class ListReferenceDataTool extends Tool
{
    use ChecksPermissions, UsesReferenceTypes;

    /** @var list<Permission> */
    protected array $permissions = [Permission::SettingsUpdate, Permission::ContactsUpdate, Permission::ImportsCreate, Permission::ImportsReview];

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->description('The list key, e.g. "areas" or "cleaning_rules". Leave out to see all lists.'),
            'search' => $schema->string()->description('Text to look for in names, slugs, aliases, keys or values. For cleaning_rules, a rule type (e.g. "header_hint") lists that type.'),
            'page' => $schema->integer()->description('Page number (50 items per page).'),
        ];
    }

    public function handle(Request $request, ReferenceItemListQuery $items): Response
    {
        if (! $this->allowed($request->user())) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'type' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $readable = $this->readableTypes($request->user());
        if (($validated['type'] ?? null) === null) {
            return Response::json(['lists' => array_values(array_map(fn ($type): array => [
                ...$type->schema(),
                'can_edit' => $this->canEdit($request->user(), $type),
            ], $readable))]);
        }

        $type = $readable[$validated['type']] ?? null;
        if ($type === null) {
            return Response::error('Unknown or unavailable list "'.$validated['type'].'". Lists you can read: '.implode(', ', array_keys($readable)).'.');
        }

        $page = $items->handle($type, new HttpRequest(array_filter([
            'filter' => isset($validated['search']) ? ['search' => $validated['search']] : null,
            'page' => $validated['page'] ?? null,
        ])));

        return Response::json([
            'type' => $type->key(),
            'can_edit' => $this->canEdit($request->user(), $type),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'items' => $page->items(),
        ]);
    }
}
