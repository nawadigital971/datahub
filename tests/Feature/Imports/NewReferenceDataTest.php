<?php

use App\Actions\Permissions\SyncPermissionsAction;
use App\Enums\IssueCode;
use App\Enums\Role;
use App\Mcp\Servers\DataHubServer;
use App\Mcp\Tools\GetBatchSummaryTool;
use App\Mcp\Tools\StageContactsTool;
use App\Models\Area;
use App\Models\Contact;
use App\Models\Country;
use App\Models\ImportBatch;
use App\Models\Region;
use App\Models\Sector;
use App\Models\StagedRow;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    $this->artisan('app:import-all');
    app(SyncPermissionsAction::class)->handle();
    $this->admin = User::factory()->create()->assignRole(Role::Admin->value);
});

function uploadNewValues(User $user, string $csv): ImportBatch
{
    test()->actingAs($user)->post('/imports', ['source_name' => 'Expo 2026', 'file' => UploadedFile::fake()->createWithContent('expo.csv', $csv)])->assertRedirect();
    $batch = ImportBatch::query()->latest('id')->firstOrFail();
    test()->actingAs($user)->put("/imports/{$batch->id}/mapping", ['column_map' => $batch->column_map])->assertSessionHasNoErrors();

    return $batch->refresh();
}

it('adds the countries, regions, areas and sectors a file names when it is committed', function () {
    $batch = uploadNewValues($this->admin, implode("\n", [
        'Name,Mobile,City,Area,Sector,Nationality',
        'Omar Saleh,+966501234567,Riyadh,Olaya,Fintech,Saudi Arabia',
        'Sara Ali,0501111111,Dubay,Al Barsa,Real Estat,Rwanda',
        'Juma Hassan,+255712345678,Dar es Salaam,,,',
        'Ali Omar,0502222222,Al Ain,,,',
    ]));

    expect($batch->column_map)->toMatchArray(['City' => 'region', 'Area' => 'area', 'Sector' => 'sector', 'Nationality' => 'nationality']);

    $riyadh = StagedRow::query()->where('row_number', 2)->sole();
    expect($riyadh->region_name)->toBe('Riyadh')
        ->and($riyadh->area_name)->toBe('Olaya')
        ->and($riyadh->sector_name)->toBe('Fintech')
        ->and($riyadh->issue_codes)->toContain(IssueCode::NewRegion->value, IssueCode::NewArea->value, IssueCode::NewSector->value);

    // Small typos match existing items instead of creating new ones.
    $sara = StagedRow::query()->where('row_number', 3)->sole();
    expect($sara->region_id)->toBe(Region::query()->where('slug', 'dubai')->value('id'))
        ->and($sara->area_id)->toBe(Area::query()->where('slug', 'al-barsha')->value('id'))
        ->and($sara->sector_id)->toBe(Sector::query()->where('slug', 'real-estate')->value('id'))
        ->and($sara->nationality_iso)->toBe('RW');

    // A city column naming an area finds the area and its emirate.
    expect(StagedRow::query()->where('row_number', 5)->sole()->area_id)->toBe(Area::query()->where('slug', 'al-ain')->value('id'));

    $this->actingAs($this->admin)->get("/imports/{$batch->id}")
        ->assertInertia(fn (Assert $page) => $page->where('newItems', fn ($items) => collect($items)->map(fn ($item) => "{$item['kind']}:{$item['name']}:{$item['parent']}")->sort()->values()->all() === [
            'area:Olaya:Riyadh',
            'country:Rwanda:',
            'country:Tanzania:',
            'region:Dar es Salaam:Tanzania',
            'region:Riyadh:Saudi Arabia',
            'sector:Fintech:',
        ]));

    $this->actingAs($this->admin)->post("/imports/{$batch->id}/commit")->assertRedirect();

    $tanzania = Country::query()->where('iso2', 'TZ')->sole();
    $region = Region::query()->where('slug', 'riyadh')->sole();
    $omar = Contact::query()->where('mobile_e164', '+966501234567')->sole();
    expect($tanzania->dial_code)->toBe('255')
        ->and($tanzania->getTranslation('name', 'ar'))->toBe('تنزانيا')
        ->and(Country::query()->where('iso2', 'RW')->exists())->toBeTrue()
        ->and($region->country->iso2)->toBe('SA')
        ->and(Region::query()->where('slug', 'dar-es-salaam')->sole()->country_id)->toBe($tanzania->id)
        ->and($omar->region_id)->toBe($region->id)
        ->and($omar->area_id)->toBe(Area::query()->where('slug', 'olaya')->where('region_id', $region->id)->value('id'))
        ->and($omar->sector_id)->toBe(Sector::query()->where('slug', 'fintech')->value('id'))
        ->and(Contact::query()->where('mobile_e164', '+255712345678')->sole()->country_id)->toBe($tanzania->id)
        ->and(Contact::query()->where('mobile_e164', '+971501111111')->sole()->nationality_country_id)->toBe(Country::query()->where('iso2', 'RW')->value('id'))
        ->and(Region::query()->where('slug', 'like', 'dubay%')->exists())->toBeFalse();
});

it('shows each row\'s sector in the preview, including sectors that will be added', function () {
    $batch = uploadNewValues($this->admin, implode("\n", [
        'Name,Mobile,Sector',
        'Omar Saleh,0501234567,Fintech',
        'Sara Ali,0501111111,Real Estate',
    ]));

    $this->actingAs($this->admin)->get("/imports/{$batch->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.data.0.sector', null)
            ->where('rows.data.0.sector_name', 'Fintech')
            ->where('rows.data.1.sector.id', Sector::query()->where('slug', 'real-estate')->value('id'))
            ->where('rows.data.1.sector_name', null));
});

it('does not add values from rows that will be skipped', function () {
    $batch = uploadNewValues($this->admin, "Name,Mobile,Sector\nOmar Saleh,0501234567,Fintech");
    StagedRow::query()->update(['action' => 3, 'action_locked' => true]);

    $this->actingAs($this->admin)->get("/imports/{$batch->id}")->assertInertia(fn (Assert $page) => $page->where('newItems', []));
    $this->actingAs($this->admin)->post("/imports/{$batch->id}/commit");

    expect(Sector::query()->where('slug', 'fintech')->exists())->toBeFalse();
});

it('tells Claude what a staged batch will add', function () {
    DataHubServer::actingAs($this->admin)->tool(StageContactsTool::class, [
        'label' => 'Riyadh expo', 'rows' => [['name_en' => 'Omar', 'phone' => '+966501234567', 'region' => 'Riyadh', 'area' => 'Olaya', 'sector' => 'Fintech']],
    ])->assertOk();

    DataHubServer::actingAs($this->admin)->tool(GetBatchSummaryTool::class, ['batch_id' => ImportBatch::query()->sole()->id])
        ->assertOk()
        ->assertSee(['added_on_commit', '"kind":"region","name":"Riyadh","parent":"Saudi Arabia"', '"kind":"area","name":"Olaya"', '"kind":"sector","name":"Fintech"']);
});
