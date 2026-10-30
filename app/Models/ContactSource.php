<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Links a contact to each source it appeared in, keeping the original row (`raw`, never modified).
 *
 * @property int $id
 * @property int $contact_id
 * @property int $source_id
 * @property int|null $import_batch_id
 * @property int|null $row_number
 * @property int|null $seen_year
 * @property array<string, mixed> $raw
 * @property Carbon|null $created_at
 */
#[Fillable(['contact_id', 'source_id', 'import_batch_id', 'row_number', 'seen_year', 'raw'])]
class ContactSource extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<Source, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * @return BelongsTo<ImportBatch, $this>
     */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
