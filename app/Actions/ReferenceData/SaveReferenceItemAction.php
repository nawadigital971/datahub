<?php

namespace App\Actions\ReferenceData;

use App\Actions\Contacts\BustAudienceCacheAction;
use App\DTOs\ReferenceData\ReferenceItemData;
use App\Queries\ReferenceData\ReferenceTypes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SaveReferenceItemAction
{
    public function __construct(private ReferenceTypes $types, private BustAudienceCacheAction $bustCache) {}

    /**
     * Create or update one reference item (values already validated) and refresh every cached lookup.
     */
    public function handle(ReferenceItemData $data): Model
    {
        $type = $this->types->find($data->type) ?? throw new InvalidArgumentException("Unknown reference list \"{$data->type}\".");
        $item = $data->id === null ? $type->newItem() : $type->find($data->id);
        if ($item === null) {
            throw (new ModelNotFoundException)->setModel($type->newItem()::class, [(int) $data->id]);
        }

        DB::transaction(function () use ($type, $item, $data): void {
            $type->fill($item, $data->values);
            $item->save();
            $type->saved($item, $data->userId);
        });

        $this->bustCache->handle();

        return $item;
    }
}
