<?php

namespace App\Queries\Contacts;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One contact with everything known about it, for the details panel.
 */
class ContactDetailsQuery
{
    /** Newest history entries shown in the panel. */
    public const int HISTORY_LIMIT = 100;

    public function handle(Contact $contact): Contact
    {
        return $contact->load([
            'phones' => fn (HasMany $query): HasMany => $query->orderByDesc('is_primary')->orderBy('id'),
            'emails' => fn (HasMany $query): HasMany => $query->orderByDesc('is_primary')->orderBy('id'),
            'sourceLinks' => fn (HasMany $query): HasMany => $query->with(['source', 'importBatch:id,original_filename,headers'])->orderByDesc('id'),
            'events' => fn (HasMany $query): HasMany => $query->with('user:id,name')->orderByDesc('occurred_at')->orderByDesc('id')->limit(self::HISTORY_LIMIT + 1),
        ]);
    }
}
