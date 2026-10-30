<?php

namespace App\Models;

use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * Countries (iso2, dial code), used for nationality and phone-derived country.
 *
 * @property int $id
 * @property string $iso2
 * @property string $dial_code
 * @property list<string> $aliases Other spellings used for matching.
 * @property string $name Translated for the current locale (all locales via getTranslations()).
 */
#[Fillable(['iso2', 'dial_code', 'name', 'aliases'])]
#[WithoutTimestamps]
#[Translatable('name')]
class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
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
     * @return HasMany<Region, $this>
     */
    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
    }
}
