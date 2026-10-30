<?php

use App\Actions\Permissions\SyncPermissionsAction;
use App\Enums\ExportChannel;
use App\Enums\ExportStatus;
use App\Enums\Role;
use App\Jobs\GenerateExportJob;
use App\Models\Contact;
use App\Models\Export;
use App\Models\Region;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    app(SyncPermissionsAction::class)->handle();
    Storage::fake('local');
});

function exportUser(Role $role = Role::Marketer): User
{
    return User::factory()->create()->assignRole($role->value);
}

describe('store', function () {
    it('logs the export with its purpose and filters and queues the file', function () {
        Queue::fake();
        $user = exportUser();

        $this->actingAs($user)->from('/audience')
            ->post('/exports', [
                'channel' => 'meta',
                'hashed' => true,
                'purpose' => 'Ramadan launch lookalike audience',
                'filters' => ['region' => '2', 'has_mobile' => '1'],
            ])
            ->assertRedirect('/audience')
            ->assertInertiaFlash('export_id', Export::query()->value('id'));

        $export = Export::query()->sole();
        expect($export->user_id)->toBe($user->id)
            ->and($export->channel)->toBe(ExportChannel::Meta)
            ->and($export->hashed)->toBeTrue()
            ->and($export->status)->toBe(ExportStatus::Queued)
            ->and($export->purpose)->toBe('Ramadan launch lookalike audience')
            ->and($export->filters)->toEqual(['region' => '2', 'has_mobile' => '1']);
        Queue::assertPushed(GenerateExportJob::class, fn (GenerateExportJob $job) => $job->exportId === $export->id);
    });

    it('never hashes channels that need readable data', function () {
        Queue::fake();

        $this->actingAs(exportUser())->post('/exports', [
            'channel' => 'whatsapp', 'hashed' => true, 'purpose' => 'Customer service follow-up', 'filters' => [],
        ]);

        expect(Export::query()->sole()->hashed)->toBeFalse();
    });

    it('requires a purpose', function () {
        $this->actingAs(exportUser())
            ->post('/exports', ['channel' => 'email', 'purpose' => '', 'filters' => []])
            ->assertSessionHasErrors(['purpose' => 'Say what this export is for. Every export is logged with its purpose.']);

        expect(Export::query()->count())->toBe(0);
    });

    it('rejects an unknown channel', function () {
        $this->actingAs(exportUser())
            ->post('/exports', ['channel' => 'fax', 'purpose' => 'Testing an unknown channel', 'filters' => []])
            ->assertSessionHasErrors('channel');
    });

    it('forbids users without exports.create', function () {
        $this->actingAs(exportUser(Role::Viewer))
            ->post('/exports', ['channel' => 'email', 'purpose' => 'Not allowed to do this', 'filters' => []])
            ->assertForbidden();
    });
});

describe('preview', function () {
    it('shows the first rows masked for users who may not see full contact details', function () {
        Contact::factory()->create(['name_en' => 'Ahmed Ali', 'mobile_e164' => '+971501234567']);

        $this->actingAs(exportUser())
            ->getJson('/exports/preview?channel=whatsapp')
            ->assertOk()
            ->assertJsonPath('data.columns', ['phone', 'name', 'language'])
            ->assertJsonPath('data.rows.0', ['+971 50 ••• ••67', 'Ahmed Ali', 'en'])
            ->assertJsonPath('data.total', 1);
    });

    it('shows full values to users with contacts.view_unmasked', function () {
        Contact::factory()->create(['name_en' => 'Ahmed Ali', 'mobile_e164' => '+971501234567']);

        $this->actingAs(exportUser(Role::Admin))
            ->getJson('/exports/preview?channel=sms')
            ->assertJsonPath('data.rows.0', ['971501234567', 'Ahmed Ali']);
    });

    it('applies the audience filters', function () {
        $dubai = Region::factory()->create();
        Contact::factory()->create(['region_id' => $dubai->id]);
        Contact::factory()->create();

        $this->actingAs(exportUser())
            ->getJson("/exports/preview?channel=calls&filter[region]={$dubai->id}")
            ->assertJsonCount(1, 'data.rows');
    });
});

describe('download', function () {
    beforeEach(function () {
        $this->owner = exportUser();
        Contact::factory()->create();
        $this->export = Export::factory()->for($this->owner)->create(['channel' => ExportChannel::Email]);
        GenerateExportJob::dispatchSync($this->export->id);
    });

    it('lets the creator download the file and records the first download', function () {
        $this->actingAs($this->owner)
            ->get("/exports/{$this->export->id}/download")
            ->assertOk()
            ->assertDownload("nawaa-email-export-{$this->export->id}.csv");

        expect($this->export->fresh()->downloaded_at)->not->toBeNull();
    });

    it('lets people who can see the export log download it', function () {
        $this->actingAs(exportUser(Role::Admin))
            ->get("/exports/{$this->export->id}/download")
            ->assertOk();
    });

    it('forbids other users without the export log permission', function () {
        $this->actingAs(exportUser(Role::DataEditor))
            ->get("/exports/{$this->export->id}/download")
            ->assertForbidden();
    });

    it('returns 404 once the file has been purged', function () {
        $this->export->clearMediaCollection('file');

        $this->actingAs($this->owner)
            ->get("/exports/{$this->export->id}/download")
            ->assertNotFound();
    });
});

describe('status', function () {
    it('reports progress to the creator', function () {
        $owner = exportUser();
        $export = Export::factory()->for($owner)->create(['status' => ExportStatus::Running]);

        $this->actingAs($owner)
            ->getJson("/exports/{$export->id}")
            ->assertOk()
            ->assertJsonPath('data.status.key', 'running')
            ->assertJsonPath('data.is_finished', false);
    });

    it('hides other people\'s exports from users without the log permission', function () {
        $export = Export::factory()->create();

        $this->actingAs(exportUser(Role::Viewer))->getJson("/exports/{$export->id}")->assertForbidden();
    });
});

describe('index', function () {
    it('lists exports newest first with a readable audience summary', function () {
        $dubai = Region::factory()->create(['name' => ['en' => 'Dubai']]);
        Export::factory()->create(['purpose' => 'Older', 'created_at' => now()->subDay()]);
        Export::factory()->create(['purpose' => 'Newer', 'filters' => ['region' => (string) $dubai->id, 'has_email' => '1']]);

        $this->actingAs(exportUser())->get('/exports')
            ->assertInertia(fn (Assert $page) => $page
                ->component('exports/Index')
                ->has('exports.data', 2)
                ->where('exports.data.0.purpose', 'Newer')
                ->where('exports.data.0.audience', ['Emirate: Dubai', 'Must: Has email'])
                ->where('exports.data.1.audience', ['Everyone (no filters)']));
    });

    it('filters by channel', function () {
        Export::factory()->create(['channel' => ExportChannel::Sms]);
        Export::factory()->create(['channel' => ExportChannel::Email]);

        $this->actingAs(exportUser())->get('/exports?filter[channel]=sms')
            ->assertInertia(fn (Assert $page) => $page
                ->has('exports.data', 1)
                ->where('exports.data.0.channel.key', 'sms'));
    });

    it('forbids users without exports.view_log', function () {
        $this->actingAs(exportUser(Role::DataEditor))->get('/exports')->assertForbidden();
    });
});
