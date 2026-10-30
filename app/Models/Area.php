<?php

namespace App\Models;

use Database\Factories\AreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * Districts inside a region. `aliases` holds Arabic and English spellings used for matching.
 *
 * @property int $id
 * @property int $region_id
 * @property string $slug
 * @property string $name Translated for the current locale (all locales via getTranslations()).
 * @property list<string> $aliases
 */
#[Fillable(['region_id', 'slug', 'name', 'aliases'])]
#[WithoutTimestamps]
#[Translatable('name')]
class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory, HasTranslations;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aliases' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
