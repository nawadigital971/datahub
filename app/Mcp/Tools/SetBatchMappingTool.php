<?php

namespace App\Mcp\Tools;

use App\Actions\Imports\ConfirmMappingAction;
use App\DTOs\Imports\ColumnMappingData;
use App\Enums\ImportOrigin;
use App\Enums\Permission;
use App\Http\Requests\Imports\ConfirmMappingRequest;
use App\Mcp\Concerns\ChecksPermissions;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('set_batch_mapping')]
#[Description('Set where each column of an uploaded file goes (header => target, every header of the file) and analyse the file again. Optionally save the mapping as a named template so files with the same headers are mapped automatically. Any edits already made in the preview are replaced. Poll get_batch_summary until the status is "Ready".')]
class SetBatchMappingTool extends Tool
{
    use ChecksPermissions;

    /** @var list<Permission> */
    protected array $permissions = [Permission::ImportsCreate];

    public function schema(JsonSchema $schema): array
    {
        return [
            'batch_id' => $schema->integer()->required(),
            'column_map' => $schema->object()->description('Every column header of the file => a target from get_batch_mapping, e.g. {"Mobile No.":"phone","Full Name":"name","Ref":"ignore"}.')->required(),
            'save_as_template' => $schema->string()->description('Save this mapping as a template with this name (optional).'),
        ];
    }

    public function handle(Request $request, ConfirmMappingAction $action): Response
    {
        if (! $this->allowed($request->user())) {
            return $this->forbidden();
        }

        $batch = ImportBatch::query()->find((int) $request->validate(['batch_id' => ['required', 'integer']])['batch_id']);
        if ($batch === null || $batch->origin !== ImportOrigin::Upload) {
            return Response::error('No uploaded file with this batch id.');
        }
        if (! in_array($batch->status, ConfirmMappingRequest::MAPPABLE, true) || $batch->committed_by !== null) {
            return Response::error("Batch {$batch->id} is {$batch->status->label()}; its mapping cannot be changed now.");
        }

        $user = $request->user();
        $validated = $request->validate([
            'column_map' => ['required', 'array', ConfirmMappingRequest::columnMapRule($batch->headers ?? [])],
            'save_as_template' => [
                'nullable', 'string', 'max:100',
                ...($user instanceof User && $user->can(Permission::SettingsUpdate->value) ? [] : [Rule::unique('mapping_templates', 'name')]),
            ],
        ], ['save_as_template.unique' => 'A template with this name already exists. Choose another name.']);

        $missing = array_diff($batch->headers ?? [], array_keys($validated['column_map']));
        if ($missing !== []) {
            return Response::error('Give a target for every column (use "ignore" to skip one). Missing: '.implode(', ', $missing).'.');
        }

        /** @var array<string, string> $columnMap */
        $columnMap = $validated['column_map'];
        $saveAs = is_string($validated['save_as_template'] ?? null) && trim($validated['save_as_template']) !== '' ? trim($validated['save_as_template']) : null;
        $action->handle($batch, new ColumnMappingData($columnMap, null, $saveAs));

        return Response::json([
            'batch_id' => $batch->id,
            'status' => $batch->refresh()->status->label(),
            'template_saved' => $saveAs,
            'note' => 'The file is being analysed again. Call get_batch_summary until the status is "Ready".',
            'review_url' => $this->reviewUrl($batch),
        ]);
    }
}
