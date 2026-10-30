<?php

namespace App\Mcp\Concerns;

use App\Models\ImportBatch;
use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * MCP tools act as the token's owner: every call checks that user's permissions (tools the user may not
 * use are also hidden from the tool list).
 */
trait ChecksPermissions
{
    public function shouldRegister(Request $request): bool
    {
        return $this->allowed($request->user());
    }

    /**
     * Any one of the tool's $permissions lets the user use it.
     */
    protected function allowed(mixed $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        foreach ($this->permissions as $permission) {
            if ($user->can($permission->value)) {
                return true;
            }
        }

        return false;
    }

    protected function forbidden(): Response
    {
        return Response::error('Your account does not have permission to use this tool.');
    }

    protected function reviewUrl(ImportBatch $batch): string
    {
        return route('imports.show', $batch);
    }

    /**
     * @return array<string, mixed>
     */
    protected function batchSummary(ImportBatch $batch): array
    {
        return [
            'batch_id' => $batch->id,
            'label' => $batch->original_filename,
            'source' => $batch->source?->name,
            'status' => $batch->status->label(),
            'counts' => [
                'total' => $batch->total_rows,
                'clean' => $batch->clean_rows,
                'warnings' => $batch->warning_rows,
                'errors' => $batch->error_rows,
                'new' => $batch->new_rows,
                'matches_existing_contact' => $batch->merge_rows,
                'duplicate_in_batch' => $batch->dup_in_batch_rows,
                'conflict' => $batch->conflict_rows,
                'will_skip' => $batch->skipped_rows,
            ],
            'review_url' => $this->reviewUrl($batch),
            'note' => 'Nothing is imported until a person reviews and commits this batch in the dashboard.',
        ];
    }
}
