<?php

namespace App\Models;

use App\Enums\PhoneType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Every phone number of a contact (E.164, globally unique — the main dedup key).
 *
 * @property int $id
 * @property int $contact_id
 * @property string $e164
 * @property PhoneType $type
 * @property int|null $country_id
 * @property bool $is_primary
 * @property Carbon|null $created_at
 */
#[Fillable(['contact_id', 'e164', 'type', 'country_id', 'is_primary'])]
class ContactPhone extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PhoneType::class,
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
