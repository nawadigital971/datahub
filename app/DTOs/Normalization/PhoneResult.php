<?php

namespace App\DTOs\Normalization;

use App\Enums\PhoneType;

final readonly class PhoneResult
{
    public function __construct(
        public string $raw,
        public ?string $e164,
        public ?PhoneType $type,
        public ?string $countryIso,
    ) {}

    public function isValid(): bool
    {
        return $this->e164 !== null;
    }
}
