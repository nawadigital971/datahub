<?php

namespace App\DTOs\Normalization;

use App\Enums\CallOutcome;

final readonly class NotesResult
{
    /**
     * @param  list<string>  $landlines  Numbers labelled "Landline: …".
     * @param  list<string>  $phones  Other phone-like numbers found in the text.
     * @param  list<string>  $emails
     */
    public function __construct(
        public ?CallOutcome $callOutcome = null,
        public array $landlines = [],
        public array $phones = [],
        public array $emails = [],
        public ?string $contractNo = null,
        public ?string $agent = null,
        public bool $verifyTransliteration = false,
    ) {}
}
