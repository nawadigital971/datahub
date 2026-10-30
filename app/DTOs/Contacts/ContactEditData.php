<?php

namespace App\DTOs\Contacts;

/**
 * One correction to one contact field (already validated with ContactFieldRules).
 */
final readonly class ContactEditData
{
    public function __construct(
        public int $contactId,
        public string $field,
        public string|int|bool|null $value,
    ) {}

    /**
     * @param  array{contact_id: int, field: string, value: string|int|bool|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['contact_id'], $data['field'], $data['value']);
    }
}
