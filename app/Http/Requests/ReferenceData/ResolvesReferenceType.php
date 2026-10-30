<?php

namespace App\Http\Requests\ReferenceData;

use App\Models\User;
use App\Queries\ReferenceData\ReferenceTypes;
use App\Queries\ReferenceData\Types\ReferenceType;
use Illuminate\Database\Eloquent\Model;

/**
 * The reference list named in the route ({type}); the first list the user may edit when none is named.
 */
trait ResolvesReferenceType
{
    /**
     * @return array<string, ReferenceType<Model>>
     */
    public function allowedTypes(): array
    {
        $user = $this->user();

        return array_filter(
            app(ReferenceTypes::class)->all(),
            fn (ReferenceType $type): bool => $user instanceof User && $user->can($type->permission()->value),
        );
    }

    public function typeKey(): string
    {
        $key = $this->route('type');

        return is_string($key) && $key !== '' ? $key : (string) array_key_first($this->allowedTypes());
    }

    /**
     * @return ReferenceType<Model>|null
     */
    public function referenceType(): ?ReferenceType
    {
        return $this->allowedTypes()[$this->typeKey()] ?? null;
    }

    public function authorize(): bool
    {
        return $this->referenceType() !== null;
    }
}
