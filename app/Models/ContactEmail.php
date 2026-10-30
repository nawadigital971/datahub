<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Every email of a contact (lowercased, globally unique — the second dedup key).
 *
 * @property int $id
 * @property int $contact_id
 * @property string $email
 * @property bool $is_primary
 * @property Carbon|null $created_at
 */
#[Fillable(['contact_id', 'email', 'is_primary'])]
class ContactEmail extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
