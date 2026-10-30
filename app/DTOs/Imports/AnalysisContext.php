<?php

namespace App\DTOs\Imports;

final readonly class AnalysisContext
{
    /**
     * @param  int|null  $sourceYear  Collection year of the source, used when a row has no date.
     * @param  int  $staleBefore  Rows last seen before this year are flagged as stale.
     */
    public function __construct(
        public ?int $sourceYear,
        public int $staleBefore,
    ) {}
}
