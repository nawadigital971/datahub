<?php

use App\Actions\Permissions\SyncPermissionsAction;
use App\Enums\ContactEventType;
use App\Enums\ImportBatchStatus;
use App\Enums\IssueCode;
use App\Enums\Role;
use App\Mcp\Servers\DataHubServer;
use App\Mcp\Tools\DeleteReferenceDataTool;
use App\Mcp\Tools\FindContactsTool;
use App\Mcp\Tools\GetBatchMappingTool;
use App\Mcp\Tools\ListReferenceDataTool;
use App\Mcp\Tools\RecheckBatchTool;
use App\Mcp\Tools\SaveReferenceDataTool;
use App\Mcp\Tools\SetBatchMappingTool;
use App\Mcp\Tools\StageContactsTool;
use App\Mcp\Tools\UpdateContactsTool;
use App\Models\Area;
use App\Models\CleaningRule;
use App\Models\Contact;
use App\Models\ContactEvent;
use App\Models\ImportBatch;
use App\Models\MappingTemplate;
use App\Models\Region;
use App\Models\Source;
use App\Models\StagedRow;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->artisan('app:import-all');
    app(SyncPermissionsAction::class)->handle();
    $this->admin = User::factory()->create()->assignRole(Role::Admin->value);
});

describe('reference data', function () {
    it('describes every list and what its fields mean', function () {
        DataHubServer::actingAs($this->admin)->tool(ListReferenceDataTool::class)
            ->assertOk()
            ->assertSee(['"key":"cleaning_rules"', '"key":"do_not_contact"', 'header_hint', '"can_edit":true']);
    });

    it('lists items with their ids and values', function () {
        DataHubServer::actingAs($this->admin)->tool(ListReferenceDataTool::class, ['type' => 'regions', 'search' => 'DXB'])
            ->assertOk()
            ->assertSee(['"total":1', '"slug":"dubai"']);
    });

    it('adds an alias to an area and reports bad items one by one', function () {
        $area = Area::query()->where('slug', 'al-barsha')->sole();
        $values = [
            'name' => $area->getTranslations('name'),
            'region_id' => $area->region_id,
            'slug' => $area->slug,
            'aliases' => [...$area->aliases, 'Barsha Hts'],
        ];

        DataHubServer::actingAs($this->admin)->tool(SaveReferenceDataTool::class, [
            'type' => 'areas',
            'items' => [
                ['id' => $area->id, 'values' => $values],
                ['values' => ['name' => ['en' => ''], 'region_id' => 999]],
            ],
        ])->assertOk()->assertSee(['"ok":true', '"ok":false', 'The selected region id is invalid.']);

        expect($area->refresh()->aliases)->toContain('Barsha Hts');
    });

    it('lets importers read the lists but not change them', function () {
        $editor = User::factory()->create()->assignRole(Role::DataEditor->value);

        DataHubServer::actingAs($editor)->tool(ListReferenceDataTool::class)->assertOk()->assertDontSee('"key":"do_not_contact"');
        DataHubServer::actingAs($editor)->tool(SaveReferenceDataTool::class, [
            'type' => 'cleaning_rules', 'items' => [['values' => ['type' => 'junk_name', 'key' => 'zzqq']]],
        ])->assertHasErrors();

        expect(CleaningRule::query()->where('key', 'zzqq')->exists())->toBeFalse();
    });

    it('refuses to delete an emirate that still has areas', function () {
        $dubai = Region::query()->where('slug', 'dubai')->sole();

        DataHubServer::actingAs($this->admin)->tool(DeleteReferenceDataTool::class, ['type' => 'regions', 'id' => $dubai->id])
            ->assertHasErrors(['It still has areas']);
    });
});

describe('batches', function () {
    it('shows and changes the mapping of an uploaded file, saving it as a template', function () {
        Storage::fake('local');
        $source = Source::factory()->create();
        $this->actingAs($this->admin)->post('/imports', [
            'source_id' => $source->id,
            'file' => UploadedFile::fake()->createWithContent('leads.csv', "Client,Cell,Ref\nAli Hassan,0501111111,A1\nSara,0502222222,A2"),
        ]);
        $batch = ImportBatch::query()->sole();

        DataHubServer::actingAs($this->admin)->tool(GetBatchMappingTool::class, ['batch_id' => $batch->id])
            ->assertOk()
            ->assertSee(['"header":"Cell"', '0501111111', '"target":"phone"']);

        DataHubServer::actingAs($this->admin)->tool(SetBatchMappingTool::class, ['batch_id' => $batch->id, 'column_map' => ['Client' => 'name']])
            ->assertHasErrors(['Map at least one column to Phone']);

        DataHubServer::actingAs($this->admin)->tool(SetBatchMappingTool::class, [
            'batch_id' => $batch->id,
            'column_map' => ['Client' => 'name', 'Cell' => 'phone', 'Ref' => 'ignore'],
            'save_as_template' => 'Client lists',
        ])->assertOk()->assertSee('"template_saved":"Client lists"');

        expect($batch->refresh()->status)->toBe(ImportBatchStatus::Ready)
            ->and(StagedRow::query()->where('import_batch_id', $batch->id)->count())->toBe(2)
            ->and(MappingTemplate::query()->where('name', 'Client lists')->sole()->column_map)->toEqual(['Client' => 'name', 'Cell' => 'phone', 'Ref' => 'ignore']);
    });

    it('re-checks a staged batch after a rule was added, keeping every row', function () {
        DataHubServer::actingAs($this->admin)->tool(StageContactsTool::class, [
            'label' => 'Event leads', 'rows' => [['name_en' => 'Omar', 'phone' => '0501234567', 'email' => 'omar@gmial.co'], ['name_en' => 'Huda', 'phone' => '0507654321']],
        ])->assertOk();
        $batch = ImportBatch::query()->sole();
        expect(StagedRow::query()->where('row_number', 1)->sole()->issue_codes)->not->toContain(IssueCode::EmailTypo->value);

        DataHubServer::actingAs($this->admin)->tool(SaveReferenceDataTool::class, [
            'type' => 'cleaning_rules', 'items' => [['values' => ['type' => 'email_domain_fix', 'key' => 'gmial.co', 'value' => 'gmail.com']]],
        ])->assertOk()->assertSee('"ok":true');
        app()->forgetScopedInstances();

        DataHubServer::actingAs($this->admin)->tool(RecheckBatchTool::class, ['batch_id' => $batch->id])->assertOk();

        expect($batch->refresh()->status)->toBe(ImportBatchStatus::Ready)
            ->and($batch->total_rows)->toBe(2)
            ->and(StagedRow::query()->where('row_number', 1)->sole()->issue_codes)->toContain(IssueCode::EmailTypo->value)
            ->and(StagedRow::query()->where('row_number', 2)->sole()->name_en)->toBe('Huda');
    });
});

describe('stored contacts', function () {
    it('finds contacts with phones masked for people who may not see them', function () {
        Contact::factory()->create(['name_en' => 'Mariam Khalid', 'mobile_e164' => '+971501234567', 'is_junk' => false, 'do_not_contact' => false]);

        DataHubServer::actingAs(User::factory()->create()->assignRole(Role::Marketer->value))->tool(FindContactsTool::class, ['search' => 'Mariam'])
            ->assertOk()->assertSee(['Mariam Khalid', '+971 50 ••• ••67'])->assertDontSee('+971501234567');
        DataHubServer::actingAs($this->admin)->tool(FindContactsTool::class, ['search' => 'Mariam'])
            ->assertOk()->assertSee('+971501234567');
    });

    it('corrects fields, logs each change and reports bad values', function () {
        $contact = Contact::factory()->create(['name_en' => 'Mariam Kalid', 'name_en_verified' => false, 'region_id' => null, 'area_id' => null]);
        $area = Area::query()->where('slug', 'al-barsha')->sole();

        DataHubServer::actingAs($this->admin)->tool(UpdateContactsTool::class, ['updates' => [
            ['contact_id' => $contact->id, 'field' => 'name_en', 'value' => 'Mariam Khalid'],
            ['contact_id' => $contact->id, 'field' => 'area_id', 'value' => (string) $area->id],
            ['contact_id' => $contact->id, 'field' => 'gender', 'value' => 'female'],
        ]])->assertOk()->assertSee(['"ok":true', '"ok":false', 'The selected value is invalid.']);

        $contact->refresh();
        expect($contact->name_en)->toBe('Mariam Khalid')
            ->and($contact->name_en_verified)->toBeTrue()
            ->and($contact->area_id)->toBe($area->id)
            ->and($contact->region_id)->toBe($area->region_id)
            ->and(ContactEvent::query()->where('contact_id', $contact->id)->where('type', ContactEventType::Edit)->count())->toBe(2)
            ->and(ContactEvent::query()->where('contact_id', $contact->id)->first()->meta)->toMatchArray(['field' => 'name_en', 'from' => 'Mariam Kalid', 'to' => 'Mariam Khalid', 'via' => 'mcp']);
    });

    it('is not available without contacts.update', function () {
        DataHubServer::actingAs(User::factory()->create()->assignRole(Role::Marketer->value))->tool(UpdateContactsTool::class, ['updates' => [['contact_id' => 1, 'field' => 'is_junk', 'value' => 'true']]])
            ->assertHasErrors();
    });
});
