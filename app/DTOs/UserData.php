<?php

namespace App\DTOs;

use Illuminate\Foundation\Http\FormRequest;

final readonly class UserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $role,
    ) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            trim($request->string('name')->toString()),
            strtolower(trim($request->string('email')->toString())),
            $request->string('role')->toString(),
        );
    }
}
