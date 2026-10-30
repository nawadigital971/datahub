<?php

namespace App\Mcp\Tools;

use App\Actions\Imports\RecheckImportBatchAction;
use App\Enums\Permission;
use App\Mcp\Concerns\ChecksPermissions;
use App\Models\ImportBatch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('recheck_batch')]
#[Description('Analyse a staged batch again with the current cleaning rules, areas, countries and do-not-contact list, after you changed them with save_reference_data. Uploaded files are re-read; batches you staged are rebuilt from the values you sent. Edits made with update_staged_rows are lost, so re-check first and fix rows afterwards. Poll get_batch_summary until the status is "Ready".')]
class RecheckBatchTool extends Tool
{
    use ChecksPermissions;

    /** @var list<Permission> */
    protected array $permissions = [Permission::ImportsReview];

    public function schema(JsonSchema $schema): array
    {
        return [
            'batch_id' => $schema->integer()->required(),
        ];
    }

    public function handle(Request $request, RecheckImportBatchAction $action): Response
    {
        if (! $this->allowed($request->user())) {
            return $this->forbidden();
        }

        $batch = ImportBatch::query()->find((int) $request->validate(['batch_id' => ['required', 'integer']])['batch_id']);
        if ($batch === null) {
            return Response::error('No such batch.');
        }
        if (! $batch->isRecheckable()) {
            return Response::error("Batch {$batch->id} is {$batch->status->label()} and cannot be re-checked now.");
        }

        $action->handle($batch);

        return Response::json([
            'batch_id' => $batch->id,
            'status' => $batch->refresh()->status->label(),
            'note' => 'Re-checking every row. Call get_batch_summary until the status is "Ready".',
            'review_url' => $this->reviewUrl($batch),
        ]);
    }
}
