<?php

use App\Actions\Permissions\SyncPermissionsAction;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportOrigin;
use App\Enums\Role;
use App\Mcp\Servers\DataHubServer;
use App\Mcp\Tools\GetBatchSummaryTool;
use App\Mcp\Tools\GetReferenceDataTool;
use App\Mcp\Tools\ListBatchIssuesTool;
use App\Mcp\Tools\QueryAudienceCountsTool;
use App\Mcp\Tools\StageContactsTool;
use App\Mcp\Tools\UpdateStagedRowsTool;
use App\Models\Contact;
use App\Models\ImportBatch;
use App\Models\Region;
use App\Models\Source;
use App\Models\StagedRow;
use App\Models\User;
use Laravel\Mcp\Server\Testing\TestResponse;

beforeEach(function () {
    $this->artisan('app:import-all');
    app(SyncPermissionsAction::class)->handle();
});

function mcpUser(Role $role = Role::Admin): User
{
    return User::factory()->create()->assignRole($role->value);
}

function stageSample(User $user, array $rows = [], ?int $batchId = null): TestResponse
{
    return DataHubServer::actingAs($user)->tool(StageContactsTool::class, array_filter([
        'source' => 'photographers-award-entrants',
        'label' => 'Photographers form – Oct 2026',
        'batch_id' => $batchId,
        'rows' => $rows ?: [
            ['name_en' => 'Omar Saleh', 'phone' => '050 123 4567', 'email' => 'omar@gamil.com', 'address' => 'Deira, Dubai'],
            ['name' => 'فاطمة علي', 'phone' => '+20 100 123 4567', 'nationality' => 'Egyptian'],
            ['name_en' => 'No Way To Reach'],
        ],
    ]));
}

describe('stage_contacts', function () {
    it('stages rows into a review batch without importing anything', function () {
        $user = mcpUser();

        stageSample($user)
            ->assertOk()
            ->assertSee(['"total":3', '"errors":1', 'review_url', 'Nothing is imported until a person reviews and commits']);

        $batch = ImportBatch::query()->sole();
        expect($batch->origin)->toBe(ImportOrigin::Mcp)
            ->and($batch->status)->toBe(ImportBatchStatus::Ready)
            ->and($batch->user_id)->toBe($user->id)
            ->and(StagedRow::query()->where('row_number', 1)->sole()->mobile_e164)->toBe('+971501234567')
            ->and(StagedRow::query()->where('row_number', 2)->sole()->name_ar)->toBe('فاطمة علي')
            ->and(Contact::query()->count())->toBe(0);
    });

    it('adds rows to the caller\'s own batch, numbering after the existing ones', function () {
        $user = mcpUser();
        stageSample($user);
        $batch = ImportBatch::query()->sole();

        stageSample($user, [['name_en' => 'Late Row', 'phone' => '0559998877']], $batch->id)->assertOk()->assertSee('"total":4');

        expect(StagedRow::query()->where('import_batch_id', $batch->id)->max('row_number'))->toBe(4);
    });

    it('refuses to add rows to someone else\'s batch', function () {
        stageSample(mcpUser());
        $batch = ImportBatch::query()->sole();

        stageSample(mcpUser(), [['phone' => '0501111111']], $batch->id)
            ->assertHasErrors(['You can only add rows to your own MCP batch while it is ready for review.']);

        expect(StagedRow::query()->count())->toBe(3);
    });

    it('refuses employee data', function () {
        DataHubServer::actingAs(mcpUser())->tool(StageContactsTool::class, [
            'source' => 'photographers-award-entrants', 'label' => 'Waseet Staff Info for Medical Insurance', 'rows' => [['phone' => '0501111111']],
        ])->assertHasErrors(['Employee data']);
    });

    it('is not available to users who cannot create imports', function () {
        DataHubServer::actingAs(mcpUser(Role::Marketer))
            ->tool(StageContactsTool::class, ['source' => 'x', 'label' => 'x', 'rows' => [['phone' => '1']]])
            ->assertHasErrors();

        expect(ImportBatch::query()->count())->toBe(0);
    });
});

describe('review tools', function () {
    beforeEach(function () {
        $this->user = mcpUser();
        stageSample($this->user);
        $this->batch = ImportBatch::query()->sole();
    });

    it('summarizes a batch with its issue counts', function () {
        DataHubServer::actingAs($this->user)->tool(GetBatchSummaryTool::class, ['batch_id' => $this->batch->id])
            ->assertOk()
            ->assertSee(['"total":3', '"email_typo":1', '"missing_contact_method":1']);
    });

    it('lists rows with a given issue and their suggestions', function () {
        DataHubServer::actingAs($this->user)->tool(ListBatchIssuesTool::class, ['batch_id' => $this->batch->id, 'issue' => 'email_typo'])
            ->assertOk()
            ->assertSee(['"row_number":1', 'omar@gmail.com'])
            ->assertDontSee('"row_number":3');
    });

    it('fixes rows the same way as an inline edit and reports bad values per row', function () {
        $row = StagedRow::query()->where('row_number', 3)->sole();

        DataHubServer::actingAs($this->user)->tool(UpdateStagedRowsTool::class, [
            'batch_id' => $this->batch->id,
            'updates' => [
                ['row_id' => $row->id, 'field' => 'mobile_e164', 'value' => '055 777 7777'],
                ['row_id' => $row->id, 'field' => 'email', 'value' => 'not-an-email'],
            ],
        ])->assertOk()->assertSee(['"ok":true', '"action":"create"', '"ok":false', 'must be a valid email']);

        expect($row->fresh()->mobile_e164)->toBe('+971557777777')
            ->and($row->edits()->sole()->user_id)->toBe($this->user->id);
    });

    it('refuses changes once the batch is no longer under review', function () {
        $this->batch->update(['status' => ImportBatchStatus::Committing]);

        DataHubServer::actingAs($this->user)->tool(UpdateStagedRowsTool::class, [
            'batch_id' => $this->batch->id,
            'updates' => [['row_id' => StagedRow::query()->first()->id, 'field' => 'name_en', 'value' => 'X']],
        ])->assertHasErrors(['rows can only be changed while it is ready for review']);
    });
});

describe('query_audience_counts', function () {
    it('counts an audience described with readable values and never returns contact details', function () {
        $dubai = Region::query()->where('slug', 'dubai')->sole();
        Contact::factory()->create(['region_id' => $dubai->id, 'email' => 'secret@example.com']);
        Contact::factory()->create();

        DataHubServer::actingAs(mcpUser(Role::Viewer))->tool(QueryAudienceCountsTool::class, ['region' => ['Dubai'], 'include_breakdown' => true])
            ->assertOk()
            ->assertSee(['"total":1', 'Emirate: Dubai', '"value":"Dubai","count":1'])
            ->assertDontSee('Unknown area')
            ->assertDontSee('secret@example.com');
    });

    it('explains unknown values', function () {
        DataHubServer::actingAs(mcpUser())->tool(QueryAudienceCountsTool::class, ['region' => ['Atlantis']])
            ->assertHasErrors(['Unknown region “atlantis”. Call get_reference_data for valid values.']);
    });
});

it('gives the reference values Claude needs', function () {
    Source::factory()->create(['slug' => 'photographers-award-entrants']);

    DataHubServer::actingAs(mcpUser(Role::Viewer))->tool(GetReferenceDataTool::class)
        ->assertOk()
        ->assertSee(['photographers-award-entrants', 'International City', 'email_typo']);
});

describe('HTTP endpoint', function () {
    $listTools = fn (?string $token) => test()->withHeaders(array_filter(['Authorization' => $token ? "Bearer {$token}" : null]))
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);

    it('rejects requests without a token', function () use ($listTools) {
        $listTools(null)->assertUnauthorized();
        $this->post('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
    });

    it('accepts the token in the URL instead of a header', function () {
        $token = mcpUser(Role::Viewer)->createToken('Claude', ['mcp'])->plainTextToken;

        $this->postJson('/mcp?token='.urlencode($token), ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
            ->assertOk()
            ->assertJsonPath('result.tools.0.name', fn (string $name) => $name !== '');
    });

    it('rejects a wrong token in the URL', function () {
        $this->postJson('/mcp?token=1|not-a-real-token', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
            ->assertUnauthorized();
    });

    it('rejects tokens that were not issued for MCP', function () use ($listTools) {
        $listTools(mcpUser()->createToken('other', ['reports'])->plainTextToken)->assertForbidden();
    });

    it('lists only the tools the token owner may use, and never a commit tool', function () use ($listTools) {
        $this->artisan('app:issue-mcp-token', ['email' => mcpUser(Role::Viewer)->email])->assertSuccessful();
        $token = mcpUser(Role::Viewer)->createToken('Claude', ['mcp'])->plainTextToken;

        $names = collect($listTools($token)->assertOk()->json('result.tools'))->pluck('name')->sort()->values()->all();

        expect($names)->toBe(['find_contacts', 'get_reference_data', 'query_audience_counts']);

        $this->app['auth']->forgetGuards();
        $adminToken = mcpUser()->createToken('Claude', ['mcp'])->plainTextToken;
        $adminNames = collect($listTools($adminToken)->json('result.tools'))->pluck('name')->all();
        expect($adminNames)->toHaveCount(14)
            ->and(implode(',', $adminNames))->not->toContain('commit');
    });
});

it('issues MCP tokens that expire and can be revoked', function () {
    $user = mcpUser();

    $this->artisan('app:issue-mcp-token', ['email' => $user->email, '--days' => 30])->assertSuccessful();
    $token = $user->tokens()->sole();
    expect($token->abilities)->toBe(['mcp'])
        ->and($token->expires_at->isSameDay(now()->addDays(30)))->toBeTrue();

    $this->artisan('app:revoke-mcp-tokens', ['email' => $user->email])->expectsOutputToContain('Revoked 1 MCP tokens.');
    expect($user->tokens()->count())->toBe(0);
});
