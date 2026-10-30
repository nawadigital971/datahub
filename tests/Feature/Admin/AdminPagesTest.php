<?php

use App\Actions\Permissions\SyncPermissionsAction;
use App\Enums\EmailStatus;
use App\Enums\LawfulBasis;
use App\Enums\Role;
use App\Models\Brand;
use App\Models\Contact;
use App\Models\Source;
use App\Models\SourceGroup;
use App\Models\User;
use App\Settings\DataSettings;
use App\Settings\GeneralSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    app(SyncPermissionsAction::class)->handle();
});

function adminPagesUser(Role $role = Role::Admin): User
{
    return User::factory()->create()->assignRole($role->value);
}

describe('data health', function () {
    it('counts data-quality issues across contacts', function () {
        Contact::factory()->create(['is_junk' => true]);
        Contact::factory()->create(['email_status' => EmailStatus::Invalid]);
        Contact::factory()->create(['mobile_e164' => null, 'email' => null, 'last_seen_year' => 2014]);

        $this->actingAs(adminPagesUser(Role::Viewer))->get('/health')
            ->assertInertia(fn (Assert $page) => $page
                ->component('health/Index')
                ->missing('health')
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->where('health.total', 3)
                    ->where('health.issues', fn ($issues) => collect($issues)->pluck('count', 'key')->only(['junk', 'invalid_email', 'unreachable', 'stale'])->all()
                        === ['unreachable' => 1, 'invalid_email' => 1, 'junk' => 1, 'stale' => 1])));
    });

    it('forbids users without contacts.view', function () {
        $this->actingAs(User::factory()->create())->get('/health')->assertForbidden();
    });
});

describe('sources', function () {
    it('lists sources with how many contacts came from each', function () {
        $source = Source::factory()->create();
        $contact = Contact::factory()->create();
        DB::table('contact_sources')->insert(['contact_id' => $contact->id, 'source_id' => $source->id, 'raw' => '{}']);

        $this->actingAs(adminPagesUser(Role::Viewer))->get('/sources')
            ->assertInertia(fn (Assert $page) => $page->where('sources.0.contacts', 1)->where('sources.0.slug', $source->slug));
    });

    it('lets admins add a source with a unique slug and record its lawful basis', function () {
        $group = SourceGroup::factory()->create();
        Source::factory()->create(['slug' => 'gala-subscribers']);

        $this->actingAs(adminPagesUser())->post('/sources', [
            'name' => ['en' => 'Gala Subscribers', 'ar' => 'مشتركو جالا'],
            'source_group_id' => $group->id,
            'collected_year' => 2025,
            'lawful_basis' => LawfulBasis::Consent->value,
        ])->assertRedirect();

        $source = Source::query()->where('slug', 'gala-subscribers-2')->sole();
        expect($source->lawful_basis)->toBe(LawfulBasis::Consent)
            ->and($source->getTranslation('name', 'ar'))->toBe('مشتركو جالا');
    });

    it('updates a source', function () {
        $source = Source::factory()->create();

        $this->actingAs(adminPagesUser())->put("/sources/{$source->id}", [
            'name' => ['en' => 'Renamed'], 'source_group_id' => $source->source_group_id, 'lawful_basis' => LawfulBasis::Contract->value, 'notes' => 'Signed customers',
        ])->assertRedirect();

        expect($source->fresh()->lawful_basis)->toBe(LawfulBasis::Contract)
            ->and($source->fresh()->notes)->toBe('Signed customers');
    });

    it('requires an English name and stops users without settings.update', function () {
        $group = SourceGroup::factory()->create();

        $this->actingAs(adminPagesUser())->post('/sources', ['name' => ['en' => ''], 'source_group_id' => $group->id, 'lawful_basis' => 4])
            ->assertSessionHasErrors('name.en');
        $this->actingAs(adminPagesUser(Role::Marketer))->post('/sources', ['name' => ['en' => 'X'], 'source_group_id' => $group->id, 'lawful_basis' => 4])
            ->assertForbidden();
    });
});

describe('settings', function () {
    it('saves the site name and data rules', function () {
        $this->artisan('app:import-countries');
        $admin = adminPagesUser();

        $this->actingAs($admin)->post('/admin/settings/general', [
            'site_name' => ['en' => 'NAWAA Contacts', 'ar' => 'نواة'], 'default_theme' => 'light',
        ])->assertRedirect();
        $this->actingAs($admin)->put('/admin/settings/data', [
            'stale_data_year_threshold' => 2020, 'hash_on_export_default' => false, 'export_retention_days' => 30, 'staged_retention_days' => 14, 'default_phone_region' => 'sa',
        ])->assertSessionHasNoErrors()->assertRedirect();

        expect(app(GeneralSettings::class)->refresh()->siteName())->toBe('NAWAA Contacts')
            ->and(app(DataSettings::class)->refresh()->stale_data_year_threshold)->toBe(2020)
            ->and(app(DataSettings::class)->export_retention_days)->toBe(30)
            ->and(app(DataSettings::class)->default_phone_region)->toBe('SA');
    });

    it('stores the logo with WebP versions and keeps the favicon as uploaded', function () {
        Storage::fake('public');

        $this->actingAs(adminPagesUser())->post('/admin/settings/general', [
            'site_name' => ['en' => 'NAWAA Data Hub'],
            'default_theme' => 'light',
            'logo_light' => UploadedFile::fake()->image('logo.png', 400, 120),
            'favicon' => UploadedFile::fake()->image('favicon.png', 32, 32),
        ])->assertRedirect();

        $brand = Brand::current();
        expect($brand->getFirstMedia('logo_light')->hasGeneratedConversion('webp'))->toBeTrue()
            ->and($brand->getFirstMedia('favicon')->getGeneratedConversions()->all())->toBe([]);

        $this->get('/audience')->assertInertia(fn (Assert $page) => $page->where('brand.logo_light', fn (string $url) => str_ends_with($url, '.webp')));
    });

    it('rejects invalid data rules and is admin-only', function () {
        $this->actingAs(adminPagesUser())->put('/admin/settings/data', [
            'stale_data_year_threshold' => 1800, 'hash_on_export_default' => true, 'export_retention_days' => 0, 'staged_retention_days' => 30, 'default_phone_region' => 'XX',
        ])->assertSessionHasErrors(['stale_data_year_threshold', 'export_retention_days', 'default_phone_region']);

        $this->actingAs(adminPagesUser(Role::Marketer))->get('/admin/settings')->assertForbidden();
    });
});
