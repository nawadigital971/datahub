<?php

namespace App\Models;

use App\Casts\PostgresArray;
use App\Enums\CallOutcome;
use App\Enums\ContactType;
use App\Enums\DncReason;
use App\Enums\EmailStatus;
use App\Enums\Gender;
use Carbon\CarbonImmutable;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One row per real person or business. Phones, emails and sources live in child tables; the primary mobile/email are denormalized here for filtering.
 *
 * @property int $id
 * @property ContactType $type
 * @property string|null $name_en
 * @property string|null $name_ar
 * @property string|null $company_name
 * @property bool $name_en_verified
 * @property Gender|null $gender
 * @property bool $gender_trusted
 * @property int|null $nationality_country_id
 * @property int|null $country_id
 * @property int|null $region_id
 * @property int|null $area_id
 * @property int|null $sector_id
 * @property string|null $address_raw
 * @property string|null $mobile_e164
 * @property string|null $landline_e164
 * @property string|null $email
 * @property EmailStatus $email_status
 * @property bool $has_mobile
 * @property bool $has_uae_mobile
 * @property bool $has_email
 * @property bool $has_landline
 * @property bool $has_arabic_name
 * @property list<int> $source_ids
 * @property list<string> $other_names Other names this contact was seen under (name_en / name_ar are the main ones)
 * @property int $source_count
 * @property int|null $first_seen_year
 * @property int|null $last_seen_year
 * @property CallOutcome|null $last_call_outcome
 * @property CarbonImmutable|null $last_called_at
 * @property bool $do_not_contact
 * @property DncReason|null $dnc_reason
 * @property bool $is_junk
 * @property string $search_text
 * @property array<string, mixed>|null $extra
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['type', 'name_en', 'name_ar', 'company_name', 'other_names', 'name_en_verified', 'gender', 'gender_trusted', 'nationality_country_id', 'country_id', 'region_id', 'area_id', 'sector_id', 'address_raw', 'mobile_e164', 'landline_e164', 'email', 'email_status', 'source_ids', 'source_count', 'first_seen_year', 'last_seen_year', 'last_call_outcome', 'last_called_at', 'do_not_contact', 'dnc_reason', 'is_junk', 'extra'])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ContactType::class,
            'gender' => Gender::class,
            'name_en_verified' => 'boolean',
            'gender_trusted' => 'boolean',
            'email_status' => EmailStatus::class,
            'has_mobile' => 'boolean',
            'has_uae_mobile' => 'boolean',
            'has_email' => 'boolean',
            'has_landline' => 'boolean',
            'has_arabic_name' => 'boolean',
            'source_ids' => PostgresArray::class.':int',
            'other_names' => PostgresArray::class.':string',
            'last_call_outcome' => CallOutcome::class,
            'last_called_at' => 'immutable_datetime',
            'do_not_contact' => 'boolean',
            'dnc_reason' => DncReason::class,
            'is_junk' => 'boolean',
            'extra' => 'array',
        ];
    }

    /**
     * @return HasMany<ContactPhone, $this>
     */
    public function phones(): HasMany
    {
        return $this->hasMany(ContactPhone::class);
    }

    /**
     * @return HasMany<ContactEmail, $this>
     */
    public function emails(): HasMany
    {
        return $this->hasMany(ContactEmail::class);
    }

    /**
     * @return HasMany<ContactSource, $this>
     */
    public function sourceLinks(): HasMany
    {
        return $this->hasMany(ContactSource::class);
    }

    /**
     * @return HasMany<ContactEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ContactEvent::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function nationality(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'nationality_country_id');
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * @return BelongsTo<Sector, $this>
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }
}
