<?php

namespace App\Models;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportOrigin;
use Database\Factories\ImportBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * One upload (or MCP staging) waiting for review, then committed or discarded. The uploaded file is in the "file" media collection.
 *
 * @property int $id
 * @property int|null $source_id
 * @property string|null $source_name
 * @property ImportOrigin $origin
 * @property int $user_id
 * @property int|null $mapping_template_id
 * @property ImportBatchStatus $status
 * @property string|null $original_filename
 * @property list<string>|null $headers
 * @property array<string, string>|null $column_map
 * @property int $total_rows
 * @property int $clean_rows
 * @property int $warning_rows
 * @property int $error_rows
 * @property int $new_rows
 * @property int $merge_rows
 * @property int $dup_in_batch_rows
 * @property int $conflict_rows
 * @property int $skipped_rows
 * @property int|null $committed_by
 * @property Carbon|null $committed_at
 * @property Carbon|null $discarded_at
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['source_id', 'source_name', 'origin', 'user_id', 'mapping_template_id', 'status', 'original_filename', 'headers', 'column_map', 'total_rows', 'clean_rows', 'warning_rows', 'error_rows', 'new_rows', 'merge_rows', 'dup_in_batch_rows', 'conflict_rows', 'skipped_rows', 'committed_by', 'committed_at', 'discarded_at', 'error'])]
class ImportBatch extends Model implements HasMedia
{
    /** @use HasFactory<ImportBatchFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin' => ImportOrigin::class,
            'status' => ImportBatchStatus::class,
            'headers' => 'array',
            'column_map' => 'array',
            'committed_at' => 'datetime',
            'discarded_at' => 'datetime',
        ];
    }

    /**
     * A staged batch can be re-checked with the current cleaning rules until a commit has started.
     */
    public function isRecheckable(): bool
    {
        if ($this->committed_by !== null) {
            return false;
        }
        if ($this->origin === ImportOrigin::Upload && $this->column_map === null) {
            return false;
        }

        return in_array($this->status, [ImportBatchStatus::Ready, ImportBatchStatus::Failed], true);
    }

    public function registerMediaCollections(): void
    {
        // Contact data: private disk only, never the public one.
        $this->addMediaCollection('file')->useDisk('local')->singleFile();
    }

    /**
     * @return BelongsTo<Source, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<MappingTemplate, $this>
     */
    public function mappingTemplate(): BelongsTo
    {
        return $this->belongsTo(MappingTemplate::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function committer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'committed_by');
    }

    /**
     * @return HasMany<StagedRow, $this>
     */
    public function stagedRows(): HasMany
    {
        return $this->hasMany(StagedRow::class);
    }
}
