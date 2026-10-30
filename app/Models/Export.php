<?php

namespace App\Models;

use App\Enums\ExportChannel;
use App\Enums\ExportStatus;
use Database\Factories\ExportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Log entry for every export (who, filters, channel, purpose). The generated CSV is in the "file" media collection.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $segment_id
 * @property array<string, mixed> $filters
 * @property ExportChannel $channel
 * @property bool $hashed
 * @property int|null $row_count
 * @property ExportStatus $status
 * @property string $purpose
 * @property Carbon|null $downloaded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'segment_id', 'filters', 'channel', 'hashed', 'row_count', 'status', 'purpose', 'downloaded_at'])]
class Export extends Model implements HasMedia
{
    /** @use HasFactory<ExportFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'channel' => ExportChannel::class,
            'hashed' => 'boolean',
            'status' => ExportStatus::class,
            'downloaded_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        // Contact data: private disk only, never the public one.
        $this->addMediaCollection('file')->useDisk('local')->singleFile();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Segment, $this>
     */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }
}
