<?php

namespace App\DTOs\Normalization;

final readonly class EmailResult
{
    /**
     * @param  string|null  $email  Lowercased address as given (null when it is not an email at all).
     * @param  string|null  $suggestion  Corrected address when the domain looks like a typo (gamil.com → gmail.com).
     * @param  string|null  $website  Set instead of $email when the cell held a web address.
     */
    public function __construct(
        public string $raw,
        public ?string $email,
        public bool $valid,
        public ?string $suggestion = null,
        public ?string $website = null,
    ) {}
}
