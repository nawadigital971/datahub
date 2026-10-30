<?php

namespace App\DTOs\Normalization;

final readonly class AreaMatch
{
    public function __construct(
        public ?int $regionId,
        public ?int $areaId,
    ) {}
}
