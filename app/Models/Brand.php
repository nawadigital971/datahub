<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The single row that owns the site's logos and favicon (edited on the Settings page).
 *
 * @property int $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Brand extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const array IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];

    public const array FAVICON_TYPES = ['image/png', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/svg+xml'];

    public static function current(): self
    {
        $brand = self::query()->find(1);

        if ($brand === null) {
            $brand = new self;
            $brand->id = 1;
            $brand->save();
        }

        return $brand;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo_light')->useDisk('public')->singleFile()->acceptsMimeTypes(self::IMAGE_TYPES);
        $this->addMediaCollection('logo_dark')->useDisk('public')->singleFile()->acceptsMimeTypes(self::IMAGE_TYPES);
        $this->addMediaCollection('favicon')->useDisk('public')->singleFile()->acceptsMimeTypes(self::FAVICON_TYPES);
    }

    /**
     * Logos get a small WebP thumbnail and a full-size WebP; SVG stays SVG and the favicon is never converted.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        if ($media === null || $media->collection_name === 'favicon' || $media->mime_type === 'image/svg+xml') {
            return;
        }

        $this->addMediaConversion('thumb')->nonQueued()->format('webp')->fit(Fit::Contain, 320, 96);
        $this->addMediaConversion('webp')->nonQueued()->format('webp');
    }
}
