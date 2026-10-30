<?php

namespace App\Mcp\Tools;

use App\Enums\Permission;
use App\Mcp\Concerns\ChecksPermissions;
use App\Models\ImportBatch;
use App\Queries\Imports\BatchNewReferenceItemsQuery;
use App\Queries\Imports\StagedRowListQuery;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get_batch_summary')]
#[Description('Status and counts of an import batch (clean / warnings / errors, new / matches / duplicates / conflicts, rows that will be skipped) and how many rows have each issue.')]
#[IsReadOnly]
class GetBatchSummaryTool extends Tool
{
    use ChecksPermissions;

    /** @var list<Permission> */
    protected array $permissions = [Permission::ImportsReview, Permission::ImportsCreate];

    public function schema(JsonSchema $schema): array
    {
        return [
            'batch_id' => $schema->integer()->description('The batch id returned by stage_contacts.')->required(),
        ];
    }

    public function handle(Request $request, StagedRowListQuery $rows, BatchNewReferenceItemsQuery $newItems): Response
    {
        if (! $this->allowed($request->user())) {
            return $this->forbidden();
        }

        $validated = $request->validate(['batch_id' => ['required', 'integer', 'exists:import_batches,id']]);
        $batch = ImportBatch::query()->with('source')->findOrFail((int) $validated['batch_id']);

        return Response::json([
            ...$this->batchSummary($batch),
            'issues' => $rows->issueCounts($batch),
            'added_on_commit' => $newItems->handle($batch),
            'added_on_commit_note' => 'These countries, emirates / regions, areas and sectors are not in the lists yet and will be created when the batch is committed. If one is a misspelling, add it as an alias of the right item with save_reference_data and run recheck_batch.',
        ]);
    }
}
