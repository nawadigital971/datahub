<?php

namespace App\Mcp\Tools;

use App\Actions\Contacts\UpdateContactsAction;
use App\DTOs\Contacts\ContactEditData;
use App\Enums\Permission;
use App\Http\Requests\Contacts\ContactFieldRules;
use App\Mcp\Concerns\ChecksPermissions;
use App\Models\Contact;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('update_contacts')]
#[Description('Correct stored contacts (find them with find_contacts): English/Arabic name (the old name is kept in other_names, so nothing is lost), add_other_name (another name the person is known by, the main name stays), remove_other_name (drop a wrong spelling from other_names), company, gender ("m", "f" or empty), type (1 person, 2 business), region_id, area_id (also sets the emirate), sector_id, nationality_country_id, is_junk (true/false) or do_not_contact (a reason id: 1 opted out, 2 complaint, 3 wrong person, 4 legal, 9 other; empty to clear). Ids come from list_reference_data. Every change is logged in the contact history with your name. Phones and emails cannot be changed here; to block a number or email everywhere, add it to the do_not_contact list with save_reference_data. Up to 200 changes per call.')]
class UpdateContactsTool extends Tool
{
    use ChecksPermissions;

    public const int MAX_UPDATES = 200;

    /** @var list<Permission> */
    protected array $permissions = [Permission::ContactsUpdate];

    public function schema(JsonSchema $schema): array
    {
        return [
            'updates' => $schema->array()->min(1)->max(self::MAX_UPDATES)->items($schema->object([
                'contact_id' => $schema->integer()->required(),
                'field' => $schema->string()->enum(ContactFieldRules::FIELDS)->required(),
                'value' => $schema->string()->description('New value as text; ids as numbers; empty to clear.'),
            ]))->required(),
        ];
    }

    public function handle(Request $request, UpdateContactsAction $action): Response
    {
        if (! $this->allowed($request->user())) {
            return $this->forbidden();
        }

        $validated = $request->validate([
            'updates' => ['required', 'array', 'min:1', 'max:'.self::MAX_UPDATES],
            'updates.*.contact_id' => ['required', 'integer'],
            'updates.*.field' => ['required', 'string', 'in:'.implode(',', ContactFieldRules::FIELDS)],
            'updates.*.value' => ['nullable'],
        ]);

        $known = Contact::query()->whereKey(array_column($validated['updates'], 'contact_id'))->pluck('id')->all();
        $edits = [];
        $results = [];
        foreach ($validated['updates'] as $index => $update) {
            $field = (string) $update['field'];
            $value = ContactFieldRules::cast($field, $update['value'] ?? null);
            $validator = Validator::make(['value' => $value], ['value' => ContactFieldRules::for($field)]);

            if (! in_array((int) $update['contact_id'], $known, true)) {
                $results[$index] = ['contact_id' => $update['contact_id'], 'field' => $field, 'ok' => false, 'error' => 'No such contact.'];
            } elseif ($validator->fails()) {
                $results[$index] = ['contact_id' => $update['contact_id'], 'field' => $field, 'ok' => false, 'error' => $validator->errors()->first('value')];
            } else {
                $edits[] = new ContactEditData((int) $update['contact_id'], $field, is_bool($value) || is_int($value) || is_string($value) ? $value : null);
                $results[$index] = ['contact_id' => (int) $update['contact_id'], 'field' => $field, 'ok' => true];
            }
        }

        if ($edits !== []) {
            $userId = $request->user()?->getAuthIdentifier();
            $action->handle($edits, is_numeric($userId) ? (int) $userId : null, 'mcp');
        }

        return Response::json(['results' => array_values($results)]);
    }
}
