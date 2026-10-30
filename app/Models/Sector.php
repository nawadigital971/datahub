<?php

namespace App\Models;

use Database\Factories\SectorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\Attributes\Translatable;
use Spatie\Translatable\HasTranslations;

/**
 * Business sectors.
 *
 * @property int $id
 * @property string $slug
 * @property string $name Translated for the current locale (all locales via getTranslations()).
 */
#[Fillable(['slug', 'name'])]
#[WithoutTimestamps]
#[Translatable('name')]
class Sector extends Model
{
    /** @use HasFactory<SectorFactory> */
    use HasFactory, HasTranslations;
}
