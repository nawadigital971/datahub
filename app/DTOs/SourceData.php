<?php

namespace App\DTOs;

use App\Enums\LawfulBasis;
use Illuminate\Foundation\Http\FormRequest;

final readonly class SourceData
{
    /**
     * @param  array<string, string>  $name
     */
    public function __construct(
        public array $name,
        public ?int $groupId,
        public ?int $collectedYear,
        public LawfulBasis $lawfulBasis,
        public ?string $notes,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        /** @var array<string, string|null> $name */
        $name = $request->validated('name');
        $notes = $request->validated('notes');

        return new self(
            array_filter(array_map(fn (?string $value): string => trim((string) $value), $name)),
            $request->filled('source_group_id') ? $request->integer('source_group_id') : null,
            $request->filled('collected_year') ? $request->integer('collected_year') : null,
            LawfulBasis::from($request->integer('lawful_basis')),
            is_string($notes) && trim($notes) !== '' ? trim($notes) : null,
        );
    }
}
