<?php

namespace App\DTOs\Imports;

use App\Http\Requests\Imports\SaveMergeChoicesRequest;

final readonly class MergeChoicesData
{
    /**
     * @param  array<string, string>  $choices  field => incoming|existing
     */
    public function __construct(
        public int $contactId,
        public array $choices,
        public int $userId,
    ) {}

    public static function fromRequest(SaveMergeChoicesRequest $request): self
    {
        /** @var array<string, string> $choices */
        $choices = $request->validated('choices', []);

        return new self($request->integer('contact_id'), $choices, (int) $request->user()?->id);
    }
}
