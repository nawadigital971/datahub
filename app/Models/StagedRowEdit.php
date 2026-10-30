<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Audit trail of inline edits on staged rows.
 *
 * @property int $id
 * @property int $staged_row_id
 * @property string $field
 * @property string|null $old_value
 * @property string|null $new_value
 * @property int|null $user_id
 * @property Carbon|null $created_at
 */
#[Fillable(['staged_row_id', 'field', 'old_value', 'new_value', 'user_id'])]
class StagedRowEdit extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<StagedRow, $this>
     */
    public function stagedRow(): BelongsTo
    {
        return $this->belongsTo(StagedRow::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
