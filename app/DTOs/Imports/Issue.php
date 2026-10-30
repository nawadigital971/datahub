<?php

namespace App\DTOs\Imports;

use App\Enums\IssueCode;

final readonly class Issue
{
    public function __construct(
        public IssueCode $code,
        public string $field,
        public string $message,
        public ?string $suggestion = null,
    ) {}

    /**
     * @return array{field: string, code: string, severity: int, message: string, suggestion: string|null}
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'code' => $this->code->value,
            'severity' => $this->code->severity()->value,
            'message' => $this->message,
            'suggestion' => $this->suggestion,
        ];
    }
}
