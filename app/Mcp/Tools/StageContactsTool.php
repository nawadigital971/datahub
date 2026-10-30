<?php

namespace App\Mcp\Tools;

use App\Actions\Imports\CheckBlockedFilenameAction;
use App\Actions\Imports\StageContactsAction;
use App\DTOs\Imports\StageContactsData;
use App\Enums\Permission;
use App\Mcp\Concerns\ChecksPermissions;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('stage_contacts')]
#[Description('Stage contact rows for review. Rows are cleaned and checked exactly like uploaded files (phones to E.164, emails, Arabic/English names, areas, duplicates, do-not-contact) and wait in a review batch. Each row may name its source (the list it came from); rows without one use the batch source. Sources that do not exist yet are created when a person commits the batch in the dashboard. Up to 5,000 rows per call; pass batch_id to add more rows to your own batch.')]
class StageContactsTool extends Tool
{
    use ChecksPermissions;

    /** @var list<Permission> */
    protected array $permissions = [Permission::ImportsCreate];

    public function schema(JsonSchema $schema): array
    {
        $text = fn (string $description) => $schema->string()->description($description);

        return [
            'source' => $schema->string()->description('Name of the list these rows come from (an existing source from get_reference_data, or a new name). Used for rows that do not name their own source; defaults to the label.'),
            'label' => $schema->string()->description('Short name for this batch, shown in the dashboard, e.g. "Photographers form – Oct 2026".')->required(),
            'batch_id' => $schema->integer()->description('Optional: add these rows to one of your own MCP batches that is still ready for review.'),
            'rows' => $schema->array()->min(1)->max(StageContactsAction::MAX_ROWS_PER_CALL)->items($schema->object([
                'name_en' => $text('Name in English (Latin letters).'),
                'name_ar' => $text('Name in Arabic.'),
                'name' => $text('A name in either language; the language is detected.'),
                'company_name' => $text('Company or business name.'),
                'type' => $text('"person" or "business".'),
                'gender' => $text('"male" / "female" (or M/F, ذكر/أنثى). Leave out when unsure.'),
                'phone' => $text('Mobile or phone number(s), any format; several can be separated by "/".'),
                'landline' => $text('Landline number(s).'),
                'email' => $text('Email address(es).'),
                'nationality' => $text('Nationality or country, English or Arabic.'),
                'address' => $text('Free-text address or area, English or Arabic.'),
                'region' => $text('Emirate, e.g. "Dubai" or "الشارقة".'),
                'sector' => $text('Business sector name or slug.'),
                'seen_year' => $text('Year or date the record was collected.'),
                'notes' => $text('Notes; call-centre codes (CB, NI, SW, D) and "Landline: …" are understood.'),
                'source' => $text('The list this row came from, if different from the batch source.'),
                'extra' => $schema->object()->description('Any other fields to keep with the contact (key → value).'),
            ]))->description('The contacts to stage.')->required(),
        ];
    }

    public function handle(Request $request, StageContactsAction $action, CheckBlockedFilenameAction $blockedFilename): Response
    {
        if (! $this->allowed($request->user())) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'source' => ['nullable', 'string', 'max:120'],
            'label' => ['required', 'string', 'max:200'],
            'batch_id' => ['nullable', 'integer'],
            'rows' => ['required', 'array', 'min:1', 'max:'.StageContactsAction::MAX_ROWS_PER_CALL],
            'rows.*' => ['array'],
        ]);

        $blocked = $blockedFilename->handle($validated['label'].' '.($validated['source'] ?? ''));
        if ($blocked !== null) {
            return Response::error("This cannot be staged: {$blocked}.");
        }

        $batch = $action->handle(new StageContactsData(
            userId: (int) $request->user()?->getAuthIdentifier(),
            sourceName: isset($validated['source']) && trim($validated['source']) !== '' ? trim($validated['source']) : null,
            label: $validated['label'],
            rows: array_values($validated['rows']),
            batchId: isset($validated['batch_id']) ? (int) $validated['batch_id'] : null,
        ));

        return Response::json($this->batchSummary($batch->load('source')));
    }
}
