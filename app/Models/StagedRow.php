<?php

namespace App\Models;

use App\Casts\PostgresArray;
use App\Enums\CallOutcome;
use App\Enums\ContactType;
use App\Enums\Gender;
use App\Enums\MatchStatus;
use App\Enums\RowSeverity;
use App\Enums\StagedRowAction;
use Database\Factories\StagedRowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A parsed, editable row of an import batch. `raw` keeps the original cells and is never modified.
 *
 * @property int $id
 * @property int $import_batch_id
 * @property int $row_number
 * @property string|null $source_name
 * @property int|null $source_id
 * @property array<string, mixed> $raw
 * @property ContactType|null $type
 * @property string|null $name_en
 * @property string|null $name_ar
 * @property string|null $company_name
 * @property Gender|null $gender
 * @property int|null $nationality_country_id
 * @property string|null $mobile_e164
 * @property string|null $landline_e164
 * @property string|null $email
 * @property int|null $country_id
 * @property int|null $region_id
 * @property int|null $area_id
 * @property int|null $sector_id
 * @property string|null $address_raw
 * @property string|null $country_iso
 * @property string|null $nationality_iso
 * @property string|null $region_name
 * @property string|null $area_name
 * @property string|null $sector_name
 * @property int|null $seen_year
 * @property CallOutcome|null $call_outcome
 * @property array<string, mixed>|null $extra
 * @property list<string> $issue_codes
 * @property list<array<string, mixed>> $issues
 * @property RowSeverity $severity
 * @property MatchStatus $match_status
 * @property int|null $matched_contact_id
 * @property int|null $dup_of_row_id
 * @property StagedRowAction $action
 * @property bool $action_locked
 * @property array<string, string>|null $merge_choices
 * @property int|null $edited_by
 * @property Carbon|null $edited_at
 */
#[Fillable(['import_batch_id', 'row_number', 'source_name', 'source_id', 'raw', 'type', 'name_en', 'name_ar', 'company_name', 'gender', 'nationality_country_id', 'mobile_e164', 'landline_e164', 'email', 'country_id', 'region_id', 'area_id', 'sector_id', 'address_raw', 'country_iso', 'nationality_iso', 'region_name', 'area_name', 'sector_name', 'seen_year', 'call_outcome', 'extra', 'issue_codes', 'issues', 'severity', 'match_status', 'matched_contact_id', 'dup_of_row_id', 'action', 'action_locked', 'merge_choices', 'edited_by', 'edited_at'])]
#[WithoutTimestamps]
class StagedRow extends Model
{
    /** @use HasFactory<StagedRowFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw' => 'array',
            'type' => ContactType::class,
            'gender' => Gender::class,
            'call_outcome' => CallOutcome::class,
            'extra' => 'array',
            'issue_codes' => PostgresArray::class.':string',
            'issues' => 'array',
            'severity' => RowSeverity::class,
            'match_status' => MatchStatus::class,
            'action' => StagedRowAction::class,
            'action_locked' => 'boolean',
            'merge_choices' => 'array',
            'edited_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ImportBatch, $this>
     */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function matchedContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'matched_contact_id');
    }

    /**
     * @return BelongsTo<StagedRow, $this>
     */
    public function dupOf(): BelongsTo
    {
        return $this->belongsTo(StagedRow::class, 'dup_of_row_id');
    }

    /**
     * @return HasMany<StagedRowEdit, $this>
     */
    public function edits(): HasMany
    {
        return $this->hasMany(StagedRowEdit::class);
    }
}
