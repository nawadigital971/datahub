<?php

namespace App\Mcp\Tools;

use App\Actions\ReferenceData\DeleteReferenceItemAction;
use App\DTOs\ReferenceData\ReferenceItemData;
use App\Enums\Permission;
use App\Mcp\Concerns\ChecksPermissions;
use App\Mcp\Concerns\UsesReferenceTypes;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete_reference_data')]
#[Description('Delete one item from a reference list by id. Items still in use (an emirate with areas, a sector or area that contacts use) are refused. Only delete what the user asked you to delete.')]
#[IsDestructive]
class DeleteReferenceDataTool extends Tool
{
    use ChecksPermissions, UsesReferenceTypes;

    /** @var list<Permission> */
    protected array $permissions = [Permission::SettingsUpdate, Permission::ContactsUpdate];

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->required(),
            'id' => $schema->integer()->required(),
        ];
    }

    public function handle(Request $request, DeleteReferenceItemAction $action): Response
    {
        $type = $this->editableType($request);
        if ($type instanceof Response) {
            return $type;
        }

        $id = $request->validate(['id' => ['required', 'integer']])['id'];
        if ($type->find((int) $id) === null) {
            return Response::error("No item {$id} in {$type->key()}.");
        }

        try {
            $title = $action->handle(new ReferenceItemData($type->key(), (int) $id, [], $this->userId($request)));
        } catch (ValidationException $exception) {
            return Response::error(collect($exception->errors())->flatten()->first() ?? 'This item cannot be deleted.');
        }

        return Response::text("Deleted “{$title}” from {$type->label()}.");
    }
}
