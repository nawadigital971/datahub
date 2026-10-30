<?php

namespace App\Mcp\Tools;

use App\Enums\ImportTarget;
use App\Enums\IssueCode;
use App\Enums\Permission;
use App\Mcp\Concerns\ChecksPermissions;
use App\Models\Area;
use App\Models\Region;
use App\Models\Sector;
use App\Models\Source;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_reference_data')]
#[Description('Valid values to use with the other tools: source slugs, emirates and their areas (with ids for update_staged_rows), sectors, issue codes and the fields stage_contacts understands.')]
#[IsReadOnly]
class GetReferenceDataTool extends Tool
{
    use ChecksPermissions;

    /** @var list<Permission> */
    protected array $permissions = [Permission::ContactsView, Permission::ImportsCreate, Permission::ImportsReview];

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response
    {
        if (! $this->allowed($request->user())) {
            return $this->forbidden();
        }

        $areas = Area::query()->orderBy('slug')->get()->groupBy('region_id');

        return Response::json([
            'sources' => Source::query()->with('group')->orderBy('id')->get()->map(fn (Source $source): array => [
                'slug' => $source->slug, 'name' => $source->name, 'group' => $source->group?->name, 'collected_year' => $source->collected_year,
            ])->all(),
            'emirates' => Region::query()->orderBy('id')->get()->map(fn (Region $region): array => [
                'id' => $region->id,
                'name' => $region->name,
                'areas' => ($areas[$region->id] ?? collect())->map(fn (Area $area): array => ['id' => $area->id, 'name' => $area->name])->values()->all(),
            ])->all(),
            'sectors' => Sector::query()->orderBy('id')->get()->map(fn (Sector $sector): array => ['id' => $sector->id, 'slug' => $sector->slug, 'name' => $sector->name])->all(),
            'issue_codes' => array_map(fn (IssueCode $code): array => ['code' => $code->value, 'label' => $code->label(), 'severity' => $code->severity()->key()], IssueCode::cases()),
            'stage_contacts_fields' => array_map(
                fn (ImportTarget $target): array => ['field' => $target === ImportTarget::Address ? 'address' : $target->value, 'meaning' => $target->label()],
                array_filter(ImportTarget::cases(), fn (ImportTarget $target): bool => ! in_array($target, [ImportTarget::Ignore, ImportTarget::Extra], true)),
            ),
        ]);
    }
}
