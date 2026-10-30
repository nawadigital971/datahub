<?php

namespace App\Mcp\Tools;

use App\Actions\Imports\UpdateStagedRowAction;
use App\DTOs\Imports\StagedRowEditData;
use App\Enums\ImportBatchStatus;
use App\Enums\Permission;
use App\Http\Requests\Imports\StagedRowFieldRules;
use App\Mcp\Concerns\ChecksPermissions;
use App\Models\ImportBatch;
use App\Models\StagedRow;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('update_staged_rows')]
#[Description('Fix staged rows before a person commits them: set a parsed field (name, phone, email, emirate/area/sector id, gender, type, address) or the action (create / merge / skip). Each change is re-normalized and re-checked like an inline edit in the dashboard and logged with your name. Up to 200 changes per call. This tool cannot commit.')]
class UpdateStagedRowsTool extends Tool
{
    use ChecksPermissions;

    public const int MAX_UPDATES = 200;

    /** @var list<Permission> */
    protected array $permissions = [Permission::ImportsReview];

    public function schema(JsonSchema $schema): array
    {
        return [
            'batch_id' => $schema->integer()->required(),
            'updates' => $schema->array()->min(1)->max(self::MAX_UPDATES)->items($schema->object([
                'row_id' => $schema->integer()->required(),
                'field' => $schema->string()->enum(StagedRowFieldRules::FIELDS)->required(),
                'value' => $schema->string()->description('New value; ids as numbers in text, empty to clear. Action: "create", "merge" or "skip".'),
            ]))->required(),
        ];
    }

    public function handle(Request $request, UpdateStagedRowAction $action): Response
    {
        if (! $this->allowed($request->user())) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'batch_id' => ['required', 'integer', 'exists:import_batches,id'],
            'updates' => ['required', 'array', 'min:1', 'max:'.self::MAX_UPDATES],
            'updates.*.row_id' => ['required', 'integer'],
            'updates.*.field' => ['required', 'string', 'in:'.implode(',', StagedRowFieldRules::FIELDS)],
            'updates.*.value' => ['nullable'],
        ]);

        $batch = ImportBatch::query()->findOrFail((int) $validated['batch_id']);
        if ($batch->status !== ImportBatchStatus::Ready) {
            return Response::error("Batch {$batch->id} is {$batch->status->label()}; rows can only be changed while it is ready for review.");
        }

        $results = [];
        foreach ($validated['updates'] as $update) {
            $row = StagedRow::query()->where('import_batch_id', $batch->id)->find((int) $update['row_id']);
            if ($row === null) {
                $results[] = ['row_id' => $update['row_id'], 'ok' => false, 'error' => 'Row not found in this batch.'];

                continue;
            }

            $value = $update['value'] ?? null;
            $value = is_numeric($value) && str_ends_with($update['field'], '_id') ? (int) $value : $value;
            $validator = Validator::make(['value' => $value], ['value' => StagedRowFieldRules::for($update['field'], $row)]);
            if ($validator->fails()) {
                $results[] = ['row_id' => $row->id, 'ok' => false, 'error' => $validator->errors()->first('value')];

                continue;
            }

            $row = $action->handle($row, new StagedRowEditData(
                $update['field'],
                is_string($value) && trim($value) === '' ? null : (is_int($value) || is_string($value) ? $value : null),
                (int) $request->user()?->getAuthIdentifier(),
            ));

            $results[] = [
                'row_id' => $row->id,
                'ok' => true,
                'action' => $row->action->key(),
                'severity' => $row->severity->key(),
                'remaining_issues' => $row->issue_codes,
            ];
        }

        return Response::json([
            'results' => $results,
            'batch' => $this->batchSummary($batch->refresh()->load('source')),
        ]);
    }
}
