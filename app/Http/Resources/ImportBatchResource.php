<?php

namespace App\Http\Resources;

use App\Enums\ImportBatchStatus;
use App\Enums\Permission;
use App\Models\ImportBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ImportBatch
 */
class ImportBatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $can = fn (Permission $permission): bool => $user?->can($permission->value) ?? false;

        return [
            'id' => $this->id,
            'filename' => $this->original_filename,
            'source' => $this->source->name ?? $this->source_name,
            'user' => $this->user?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'committed_at' => $this->committed_at?->toIso8601String(),
            'status' => ['key' => strtolower($this->status->name), 'label' => $this->status->label()],
            'is_busy' => in_array($this->status, [ImportBatchStatus::Analyzing, ImportBatchStatus::Committing], true),
            'error' => $this->error,
            'mapping' => $this->mappingTemplate->name ?? ($this->column_map !== null ? 'Custom mapping' : null),
            'counts' => [
                'total' => $this->total_rows,
                'clean' => $this->clean_rows,
                'warnings' => $this->warning_rows,
                'errors' => $this->error_rows,
                'new' => $this->new_rows,
                'merge' => $this->merge_rows,
                'dup_in_batch' => $this->dup_in_batch_rows,
                'conflict' => $this->conflict_rows,
                'skipped' => $this->skipped_rows,
            ],
            'can' => [
                'review' => $this->status === ImportBatchStatus::Ready && $can(Permission::ImportsReview),
                'map' => in_array($this->status, [ImportBatchStatus::Mapping, ImportBatchStatus::Ready, ImportBatchStatus::Failed], true) && $can(Permission::ImportsCreate),
                'commit' => ($this->status === ImportBatchStatus::Ready || ($this->status === ImportBatchStatus::Failed && $this->committed_by !== null)) && $can(Permission::ImportsCommit),
                'recheck' => $this->resource->isRecheckable() && $can(Permission::ImportsReview),
                'discard' => in_array($this->status, [ImportBatchStatus::Mapping, ImportBatchStatus::Ready, ImportBatchStatus::Failed], true) && $can(Permission::ImportsDiscard),
            ],
        ];
    }
}
