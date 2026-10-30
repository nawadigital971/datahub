<?php

namespace App\Actions\Sources;

use App\Actions\Contacts\BustAudienceCacheAction;
use App\DTOs\SourceData;
use App\Models\Source;
use Illuminate\Support\Str;

class SaveSourceAction
{
    public function __construct(private BustAudienceCacheAction $bustCache) {}

    /**
     * Create a source (slug from the English name) or update an existing one.
     */
    public function handle(SourceData $data, ?Source $source = null): Source
    {
        $source ??= new Source(['slug' => $this->uniqueSlug($data->name[config('app.fallback_locale')] ?? 'source')]);

        $source->fill([
            'name' => $data->name,
            'source_group_id' => $data->groupId,
            'collected_year' => $data->collectedYear,
            'lawful_basis' => $data->lawfulBasis,
            'notes' => $data->notes,
        ])->save();

        $this->bustCache->handle();

        return $source;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'source';
        $slug = $base;
        for ($n = 2; Source::query()->where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}
