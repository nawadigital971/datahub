<?php

namespace App\Mcp\Tools;

use App\Actions\ReferenceData\SaveReferenceItemAction;
use App\DTOs\ReferenceData\ReferenceItemData;
use App\Enums\Permission;
use App\Mcp\Concerns\ChecksPermissions;
use App\Mcp\Concerns\UsesReferenceTypes;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('save_reference_data')]
#[Description('Add or change items in a reference list (see list_reference_data for the lists and their fields). Send "id" to change an item (send all its values, as returned by list_reference_data) or leave it out to add one. Examples: add an area alias, a new sector, a cleaning rule {"type":"email_domain_fix","key":"gmial.com","value":"gmail.com"}, a header hint, or a do-not-contact number {"kind":1,"value":"0501234567","reason":1}. Up to 100 items per call; each one is checked on its own. Changes apply to the next analysis; use recheck_batch to apply them to a staged batch.')]
class SaveReferenceDataTool extends Tool
{
    use ChecksPermissions, UsesReferenceTypes;

    public const int MAX_ITEMS = 100;

    /** @var list<Permission> */
    protected array $permissions = [Permission::SettingsUpdate, Permission::ContactsUpdate];

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->description('The list key, e.g. "areas", "cleaning_rules", "do_not_contact".')->required(),
            'items' => $schema->array()->min(1)->max(self::MAX_ITEMS)->items($schema->object([
                'id' => $schema->integer()->description('The item to change; leave out to add.'),
                'values' => $schema->object()->description('Field values, e.g. {"name":{"en":"Al Barsha","ar":"البرشاء"},"region_id":2,"aliases":["Barsha"]}.')->required(),
            ]))->required(),
        ];
    }

    public function handle(Request $request, SaveReferenceItemAction $action): Response
    {
        $type = $this->editableType($request);
        if ($type instanceof Response) {
            return $type;
        }

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.values' => ['required', 'array'],
        ]);

        $results = [];
        foreach ($validated['items'] as $index => $entry) {
            $id = isset($entry['id']) ? (int) $entry['id'] : null;
            $existing = $id === null ? null : $type->find($id);
            if ($id !== null && $existing === null) {
                $results[] = ['index' => $index, 'ok' => false, 'error' => "No item {$id} in {$type->key()}."];

                continue;
            }

            /** @var array<string, mixed> $values */
            $values = $entry['values'];
            $validator = Validator::make($values, $type->rules($existing, $values));
            if ($validator->fails()) {
                $results[] = ['index' => $index, 'id' => $id, 'ok' => false, 'errors' => $validator->errors()->all()];

                continue;
            }

            $item = $action->handle(new ReferenceItemData($type->key(), $id, $validator->validated(), $this->userId($request)));
            $results[] = ['index' => $index, 'ok' => true, ...$type->present($item)];
        }

        return Response::json(['type' => $type->key(), 'results' => $results]);
    }
}
