<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * How many emails to send to the email verifier. A null count is the scheduled run (capped by the
 * daily limit and the remaining daily credits); a set count is a manual run that sends exactly that
 * many, even past the daily limit (the verifier then spends instant credits).
 */
final readonly class EmailVerificationData
{
    public function __construct(public ?int $count = null) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self($request->integer('count'));
    }

    /**
     * @param  array{count?: int|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['count'] ?? null);
    }

    public function isManual(): bool
    {
        return $this->count !== null;
    }
}
