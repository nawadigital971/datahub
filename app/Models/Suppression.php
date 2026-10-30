<?php

namespace App\Models;

use App\Enums\DncReason;
use App\Enums\SuppressionKind;
use Database\Factories\SuppressionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Global do-not-contact list. Imports must never bring these phones/emails back.
 *
 * @property int $id
 * @property SuppressionKind $kind
 * @property string $value
 * @property DncReason $reason
 * @property int|null $source_id
 * @property int|null $user_id
 * @property Carbon|null $created_at
 */
#[Fillable(['kind', 'value', 'reason', 'source_id', 'user_id'])]
class Suppression extends Model
{
    /** @use HasFactory<SuppressionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => SuppressionKind::class,
            'reason' => DncReason::class,
        ];
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
}
