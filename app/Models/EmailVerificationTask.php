<?php

namespace App\Models;

use App\Enums\EmailVerificationTaskStatus;
use Database\Factories\EmailVerificationTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A bulk task sent to the email verifier (Reoon) with the emails it covers.
 *
 * @property int $id
 * @property string|null $reoon_task_id
 * @property EmailVerificationTaskStatus $status
 * @property list<string> $emails
 * @property list<int> $contact_ids
 * @property int $count_submitted
 * @property int $count_processing
 * @property array<string, int>|null $summary
 * @property string|null $error
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['reoon_task_id', 'status', 'emails', 'contact_ids', 'count_submitted', 'count_processing', 'summary', 'error', 'completed_at'])]
class EmailVerificationTask extends Model
{
    /** @use HasFactory<EmailVerificationTaskFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EmailVerificationTaskStatus::class,
            'emails' => 'array',
            'contact_ids' => 'array',
            'summary' => 'array',
            'completed_at' => 'datetime',
        ];
    }
}
