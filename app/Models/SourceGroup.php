<?php

namespace App\Models;

use Database\Factories\SourceGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * Groups of sources (Magazines, Waseet, Photography, Retail…).
 *
 * @property int $id
 * @property string $slug
 * @property string $name Translated for the current locale (all locales via getTranslations()).
 */
#[Fillable(['slug', 'name'])]
#[WithoutTimestamps]
#[Translatable('name')]
class SourceGroup extends Model
{
    /** @use HasFactory<SourceGroupFactory> */
    use HasFactory, HasTranslations;

    /**
     * @return HasMany<Source, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(Source::class);
    }
}
