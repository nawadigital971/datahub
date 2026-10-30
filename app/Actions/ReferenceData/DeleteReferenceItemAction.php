<?php

namespace App\Actions\ReferenceData;

use App\Actions\Contacts\BustAudienceCacheAction;
use App\DTOs\ReferenceData\ReferenceItemData;
use App\Queries\ReferenceData\ReferenceTypes;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class DeleteReferenceItemAction
{
    public function __construct(private ReferenceTypes $types, private BustAudienceCacheAction $bustCache) {}

    /**
     * Delete one reference item unless it is still in use. Returns its title.
     */
    public function handle(ReferenceItemData $data): string
    {
        $type = $this->types->find($data->type) ?? throw new InvalidArgumentException("Unknown reference list \"{$data->type}\".");
        $item = $data->id === null ? null : $type->find($data->id);
        if ($item === null) {
            throw (new ModelNotFoundException)->setModel($type->newItem()::class, [(int) $data->id]);
        }

        $title = $type->title($item);
        $blocker = $type->deleteBlocker($item);
        if ($blocker !== null) {
            throw ValidationException::withMessages(['item' => "“{$title}” cannot be deleted: {$blocker}"]);
        }

        $item->delete();
        $this->bustCache->handle();

        return $title;
    }
}
