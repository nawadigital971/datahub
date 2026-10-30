<?php

namespace App\Mcp\Tools;

use App\Enums\ImportOrigin;
use App\Enums\ImportTarget;
use App\Enums\Permission;
use App\Mcp\Concerns\ChecksPermissions;
use App\Models\ImportBatch;
use App\Models\MappingTemplate;
use App\Queries\Imports\ImportFileSampleQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_batch_mapping')]
#[Description('For an uploaded file: its column headers with a few sample values each, the current column mapping (header => target), the saved mapping templates and every target you can map to. Use it to check or fix how a file is read, then call set_batch_mapping.')]
#[IsReadOnly]
class GetBatchMappingTool extends Tool
{
    use ChecksPermissions;

    /** @var list<Permission> */
    protected array $permissions = [Permission::ImportsCreate, Permission::ImportsReview];

    public function schema(JsonSchema $schema): array
    {
        return [
            'batch_id' => $schema->integer()->required(),
        ];
    }

    public function handle(Request $request, ImportFileSampleQuery $samples): Response
    {
        if (! $this->allowed($request->user())) {
            return $this->forbidden();
        }

        $batch = ImportBatch::query()->with('mappingTemplate')->find((int) $request->validate(['batch_id' => ['required', 'integer']])['batch_id']);
        if ($batch === null) {
            return Response::error('No such batch.');
        }
        if ($batch->origin !== ImportOrigin::Upload) {
            return Response::error('This batch was staged over MCP, so it has no columns to map. Fix rows with update_staged_rows instead.');
        }

        $rows = $samples->handle($batch);
        $columnMap = $batch->column_map ?? [];
        $columns = [];
        foreach ($batch->headers ?? [] as $index => $header) {
            $columns[] = [
                'header' => $header,
                'target' => $columnMap[$header] ?? null,
                'samples' => array_values(array_filter(array_map(fn (array $row): string => $row[$index] ?? '', $rows), fn (string $value): bool => $value !== '')),
            ];
        }

        return Response::json([
            'batch_id' => $batch->id,
            'file' => $batch->original_filename,
            'status' => $batch->status->label(),
            'template' => $batch->mappingTemplate?->name,
            'columns' => $columns,
            'targets' => [
                ...array_map(fn (ImportTarget $target): array => ['target' => $target->value, 'meaning' => $target->label()], ImportTarget::cases()),
                ['target' => 'extra.<key>', 'meaning' => 'Keep the column as extra data under this key (lowercase letters, digits, _).'],
            ],
            'templates' => MappingTemplate::query()->orderBy('name')->get()->map(fn (MappingTemplate $template): array => [
                'id' => $template->id, 'name' => $template->name, 'column_map' => $template->column_map,
            ])->all(),
            'review_url' => $this->reviewUrl($batch),
        ]);
    }
}
