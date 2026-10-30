<?php

namespace App\Queries\Sources;

use App\Actions\Normalization\NormalizeArabicTextAction;
use App\Models\Source;
use Illuminate\Container\Attributes\Scoped;

/**
 * Finds an existing source by a name written in a file: case-insensitive, ignoring Arabic letter
 * variants and diacritics, and matching any translation or the slug ("layalina-subscribers").
 */
#[Scoped]
class SourceNameMatcher
{
    /** @var array<string, int>|null normalized name => source id */
    private ?array $index = null;

    public function __construct(private NormalizeArabicTextAction $normalize) {}

    public function find(?string $name): ?int
    {
        $key = $this->key($name);

        return $key === '' ? null : ($this->index()[$key] ?? null);
    }

    public function remember(string $name, int $sourceId): void
    {
        $this->index();
        $this->index[$this->key($name)] = $sourceId;
    }

    public function key(?string $name): string
    {
        return trim((string) preg_replace('/[\s\-_]+/u', ' ', $this->normalize->handle($name)));
    }

    /**
     * @return array<string, int>
     */
    private function index(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $this->index = [];
        foreach (Source::query()->get() as $source) {
            foreach ([...array_values($source->getTranslations('name')), $source->slug] as $name) {
                $this->index[$this->key($name)] ??= $source->id;
            }
        }

        return $this->index;
    }
}
