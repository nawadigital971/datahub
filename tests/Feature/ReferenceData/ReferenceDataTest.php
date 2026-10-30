<?php

use App\Actions\Normalization\NormalizeEmailAction;
use App\Actions\Permissions\SyncPermissionsAction;
use App\Enums\CleaningRuleType;
use App\Enums\ContactEventType;
use App\Enums\DncReason;
use App\Enums\ImportTarget;
use App\Enums\MappingLayout;
use App\Enums\Role;
use App\Enums\SuppressionKind;
use App\Models\Area;
use App\Models\CleaningRule;
use App\Models\Contact;
use App\Models\MappingTemplate;
use App\Models\Region;
use App\Models\Sector;
use App\Models\Suppression;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    app(SyncPermissionsAction::class)->handle();
    $this->artisan('app:import-all');
    $this->admin = User::factory()->create()->assignRole(Role::Admin->value);
});

it('lists the chosen list with its fields', function () {
    $this->actingAs($this->admin)->get('/admin/reference/regions')
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reference/Index')
            ->where('type.key', 'regions')
            ->where('items.total', Region::query()->count())
            ->where('types', fn ($types) => collect($types)->pluck('key')->contains('do_not_contact')));
});

it('opens the first list when none is named', function () {
    $this->actingAs($this->admin)->get('/admin/reference')
        ->assertInertia(fn (Assert $page) => $page->where('type.key', 'regions'));
});

it('searches names and aliases', function () {
    $this->actingAs($this->admin)->get('/admin/reference/regions?filter[search]=DXB')
        ->assertInertia(fn (Assert $page) => $page->where('items.total', 1)->where('items.data.0.values.slug', 'dubai'));
});

it('adds an area with a slug made from its name and its aliases', function () {
    $dubai = Region::query()->where('slug', 'dubai')->sole();

    $this->actingAs($this->admin)->post('/admin/reference/areas', ['values' => [
        'name' => ['en' => 'Dubai Hills Estate', 'ar' => 'دبي هيلز'],
        'region_id' => $dubai->id,
        'aliases' => ['Dubai Hills', ' dubai hills ', 'دبي هلز'],
    ]])->assertSessionHasNoErrors()->assertRedirect();

    $area = Area::query()->where('slug', 'dubai-hills-estate')->sole();
    expect($area->region_id)->toBe($dubai->id)
        ->and($area->aliases)->toBe(['Dubai Hills', 'دبي هلز']);
});

it('checks each list with its own rules', function () {
    $this->actingAs($this->admin)->post('/admin/reference/cleaning_rules', ['values' => [
        'type' => CleaningRuleType::FirstName->value, 'key' => 'Sami', 'value' => 'male',
    ]])->assertSessionHasErrors(['values.value' => 'Use "m" or "f".']);

    $this->actingAs($this->admin)->post('/admin/reference/cleaning_rules', ['values' => [
        'type' => CleaningRuleType::HeaderHint->value, 'key' => 'viber no', 'value' => 'phone',
    ]])->assertSessionHasNoErrors();
});

it('uses a new cleaning rule in the next analysis', function () {
    expect(app(NormalizeEmailAction::class)->handle('sara@gmial.co')[0]->suggestion)->toBeNull();

    $this->actingAs($this->admin)->post('/admin/reference/cleaning_rules', ['values' => [
        'type' => CleaningRuleType::EmailDomainFix->value, 'key' => 'GMIAL.CO', 'value' => 'gmail.com',
    ]])->assertSessionHasNoErrors();

    app()->forgetScopedInstances();
    expect(CleaningRule::query()->where('key', 'gmial.co')->exists())->toBeTrue()
        ->and(app(NormalizeEmailAction::class)->handle('sara@gmial.co')[0]->suggestion)->toBe('sara@gmail.com');
});

it('saves a mapping template from header and target pairs', function () {
    $this->actingAs($this->admin)->post('/admin/reference/mapping_templates', ['values' => [
        'name' => 'Expo visitors',
        'layout' => MappingLayout::Custom->value,
        'column_map' => [['header' => 'Mobile No.', 'target' => ImportTarget::Phone->value], ['header' => 'E-mail', 'target' => ImportTarget::Email->value]],
    ]])->assertSessionHasNoErrors();

    expect(MappingTemplate::query()->where('name', 'Expo visitors')->sole()->column_map)
        ->toEqual(['Mobile No.' => 'phone', 'E-mail' => 'email']);
});

it('does not delete what is still in use', function () {
    $dubai = Region::query()->where('slug', 'dubai')->sole();

    $this->actingAs($this->admin)->delete("/admin/reference/regions/{$dubai->id}")
        ->assertSessionHasErrors(['item' => '“Dubai” cannot be deleted: It still has areas. Delete or move them first.']);

    expect(Region::query()->whereKey($dubai->id)->exists())->toBeTrue();
});

it('deletes an unused item', function () {
    $sector = Sector::factory()->create();

    $this->actingAs($this->admin)->delete("/admin/reference/sectors/{$sector->id}")->assertSessionHasNoErrors();

    expect(Sector::query()->whereKey($sector->id)->exists())->toBeFalse();
});

it('marks every contact with a do-not-contact number and logs the opt-out', function () {
    $contact = Contact::factory()->create(['mobile_e164' => '+971501234567', 'do_not_contact' => false]);
    $other = Contact::factory()->create(['mobile_e164' => '+971509999999', 'do_not_contact' => false]);

    $this->actingAs($this->admin)->post('/admin/reference/do_not_contact', ['values' => [
        'kind' => SuppressionKind::Phone->value, 'value' => '050 123 4567', 'reason' => DncReason::Complaint->value,
    ]])->assertSessionHasNoErrors();

    $suppression = Suppression::query()->sole();
    expect($suppression->value)->toBe('+971501234567')
        ->and($suppression->user_id)->toBe($this->admin->id)
        ->and($contact->refresh()->do_not_contact)->toBeTrue()
        ->and($contact->dnc_reason)->toBe(DncReason::Complaint)
        ->and($other->refresh()->do_not_contact)->toBeFalse()
        ->and(DB::table('contact_events')->where('contact_id', $contact->id)->value('type'))->toBe(ContactEventType::OptOut->value);

    $this->actingAs($this->admin)->post('/admin/reference/do_not_contact', ['values' => [
        'kind' => SuppressionKind::Phone->value, 'value' => '+971 50 123 4567', 'reason' => DncReason::OptedOut->value,
    ]])->assertSessionHasErrors(['values.value' => '+971501234567 is already on the do-not-contact list.']);
});

it('only shows the lists a user may edit', function () {
    $editor = User::factory()->create()->givePermissionTo('contacts.update');

    $this->actingAs($editor)->get('/admin/reference')
        ->assertInertia(fn (Assert $page) => $page->where('type.key', 'do_not_contact')->has('types', 1));
    $this->actingAs($editor)->get('/admin/reference/regions')->assertForbidden();
    $this->actingAs(User::factory()->create()->assignRole(Role::Marketer->value))->get('/admin/reference/regions')->assertForbidden();
});
