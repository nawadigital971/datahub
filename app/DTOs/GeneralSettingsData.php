<?php

namespace App\DTOs;

use App\Enums\Theme;
use App\Http\Requests\Admin\UpdateGeneralSettingsRequest;
use Illuminate\Http\UploadedFile;

final readonly class GeneralSettingsData
{
    /**
     * @param  array<string, string>  $siteName
     * @param  array<string, UploadedFile>  $uploads  collection => file
     * @param  list<string>  $remove  collections to clear
     */
    public function __construct(
        public array $siteName,
        public Theme $defaultTheme,
        public array $uploads,
        public array $remove,
    ) {}

    public static function fromRequest(UpdateGeneralSettingsRequest $request): self
    {
        /** @var array<string, string|null> $names */
        $names = $request->validated('site_name');
        $uploads = [];
        foreach (['logo_light', 'logo_dark', 'favicon'] as $collection) {
            $file = $request->file($collection);
            if ($file instanceof UploadedFile) {
                $uploads[$collection] = $file;
            }
        }

        return new self(
            array_filter(array_map(fn (?string $name): string => trim((string) $name), $names)),
            Theme::from($request->string('default_theme')->toString()),
            $uploads,
            array_values(array_map('strval', (array) $request->validated('remove', []))),
        );
    }
}
