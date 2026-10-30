<?php

namespace App\Mcp\Tools;

use App\Enums\IssueCode;
use App\Enums\MatchStatus;
use App\Enums\Permission;
use App\Mcp\Concerns\ChecksPermissions;
use App\Models\ImportBatch;
use App\Models\StagedRow;
use App\Queries\Imports\StagedRowFilters;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_batch_issues')]
#[Description('List rows of a batch that have problems (or a given issue / match status), with their parsed values, issues and suggestions, so they can be fixed with update_staged_rows. Paginate with after_row.')]
#[IsReadOnly]
class ListBatchIssuesTool extends Tool
{
    use ChecksPermissions;

    public const int MAX_LIMIT = 100;

    /** @var list<Permission> */
    protected array $permissions = [Permission::ImportsReview];

    public function schema(JsonSchema $schema): array
    {
        return [
            'batch_id' => $schema->integer()->required(),
            'issue' => $schema->string()->enum(array_column(IssueCode::cases(), 'value'))->description('Only rows with this issue. Leave out for every row with any issue.'),
            'match_status' => $schema->string()->enum(array_map(fn (MatchStatus $status): string => $status->key(), MatchStatus::cases()))->description('Only rows with this match status.'),
            'after_row' => $schema->integer()->description('Return rows after this row_number (for the next page).'),
            'limit' => $schema->integer()->min(1)->max(self::MAX_LIMIT)->default(50),
        ];
    }

    public function handle(Request $request, StagedRowFilters $filters): Response
    {
        if (! $this->allowed($request->user())) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'batch_id' => ['required', 'integer', 'exists:import_batches,id'],
            'issue' => ['nullable', 'string'],
            'match_status' => ['nullable', 'string'],
            'after_row' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ]);
        $batch = ImportBatch::query()->findOrFail((int) $validated['batch_id']);
        $limit = (int) ($validated['limit'] ?? 50);

        $query = StagedRow::query()->where('import_batch_id', $batch->id)->orderBy('row_number');
        $filters->apply($query, ['issue' => $validated['issue'] ?? null, 'match' => $validated['match_status'] ?? null]);
        if (($validated['issue'] ?? null) === null && ($validated['match_status'] ?? null) === null) {
            $query->whereRaw('cardinality(issue_codes) > 0');
        }
        if (isset($validated['after_row'])) {
            $query->where('row_number', '>', (int) $validated['after_row']);
        }

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;

        return Response::json([
            'batch_id' => $batch->id,
            'rows' => $rows->take($limit)->map(fn (StagedRow $row): array => [
                'row_id' => $row->id,
                'row_number' => $row->row_number,
                'name_en' => $row->name_en,
                'name_ar' => $row->name_ar,
                'company_name' => $row->company_name,
                'mobile_e164' => $row->mobile_e164,
                'landline_e164' => $row->landline_e164,
                'email' => $row->email,
                'region_id' => $row->region_id,
                'area_id' => $row->area_id,
                'address_raw' => $row->address_raw,
                'match_status' => $row->match_status->key(),
                'action' => $row->action->key(),
                'issues' => $row->issues,
                'original' => $row->raw,
            ])->values()->all(),
            'next_after_row' => $hasMore ? $rows->get($limit - 1)?->row_number : null,
        ]);
    }
}
