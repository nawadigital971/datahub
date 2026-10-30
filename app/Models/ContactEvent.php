<?php

namespace App\Models;

use App\Enums\CallOutcome;
use App\Enums\ContactEventType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * History of a contact: calls, opt-outs, merges, exports and edits.
 *
 * @property int $id
 * @property int $contact_id
 * @property ContactEventType $type
 * @property CallOutcome|null $outcome
 * @property string|null $agent
 * @property CarbonImmutable $occurred_at
 * @property array<string, mixed>|null $meta
 * @property int|null $user_id
 */
#[Fillable(['contact_id', 'type', 'outcome', 'agent', 'occurred_at', 'meta', 'user_id'])]
#[WithoutTimestamps]
class ContactEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ContactEventType::class,
            'outcome' => CallOutcome::class,
            'occurred_at' => 'immutable_datetime',
            'meta' => 'array',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
