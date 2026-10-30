<?php

namespace App\Queries\Settings;

use App\Models\Brand;
use Illuminate\Support\Facades\Cache;

/**
 * Public URLs of the uploaded logos and favicon (cached; cleared when settings are saved).
 *
 * @phpstan-type Assets array{logo_light: string|null, logo_dark: string|null, favicon: string|null}
 */
class BrandAssetsQuery
{
    public const string CACHE_KEY = 'brand:assets';

    /**
     * @return Assets
     */
    public function handle(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $brand = Brand::query()->find(1);
            $url = function (string $collection) use ($brand): ?string {
                $media = $brand?->getFirstMedia($collection);
                if ($media === null) {
                    return null;
                }

                return $media->hasGeneratedConversion('webp') ? $media->getUrl('webp') : $media->getUrl();
            };

            return [
                'logo_light' => $url('logo_light'),
                'logo_dark' => $url('logo_dark'),
                'favicon' => $url('favicon'),
            ];
        });
    }
}
