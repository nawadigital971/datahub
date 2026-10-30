<?php

namespace App\Models;

use App\Enums\LawfulBasis;
use Database\Factories\SourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * A list the contacts came from (one Excel file or campaign).
 *
 * @property int $id
 * @property int|null $source_group_id
 * @property string $slug
 * @property string $name Translated for the current locale (all locales via getTranslations()).
 * @property int|null $collected_year
 * @property LawfulBasis $lawful_basis
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['source_group_id', 'slug', 'name', 'collected_year', 'lawful_basis', 'notes'])]
#[Translatable('name')]
class Source extends Model
{
    /** @use HasFactory<SourceFactory> */
    use HasFactory, HasTranslations;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lawful_basis' => LawfulBasis::class,
        ];
    }

    /**
     * @return BelongsTo<SourceGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(SourceGroup::class, 'source_group_id');
    }

    /**
     * @return HasMany<ContactSource, $this>
     */
    public function contactSources(): HasMany
    {
        return $this->hasMany(ContactSource::class);
    }
}
