<?php

use App\Actions\Contacts\RefreshFacetCountsAction;
use App\Actions\Permissions\SyncPermissionsAction;
use App\Enums\EmailStatus;
use App\Enums\Gender;
use App\Enums\Role;
use App\Models\Contact;
use App\Models\Region;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    app(SyncPermissionsAction::class)->handle();
    $this->dubai = Region::factory()->create(['slug' => 'dubai', 'name' => ['en' => 'Dubai']]);
    $this->sharjah = Region::factory()->create(['slug' => 'sharjah', 'name' => ['en' => 'Sharjah']]);
});

function audienceUser(Role $role = Role::Marketer): User
{
    return User::factory()->create()->assignRole($role->value);
}

it('lists contacts matching the filters, newest first', function () {
    $older = Contact::factory()->create(['region_id' => $this->dubai->id]);
    $newer = Contact::factory()->create(['region_id' => $this->dubai->id]);
    Contact::factory()->create(['region_id' => $this->sharjah->id]);

    $this->actingAs(audienceUser())
        ->get('/audience?filter[region]='.$this->dubai->id)
        ->assertInertia(fn (Assert $page) => $page
            ->component('audience/Index')
            ->where('filters.region', [$this->dubai->id])
            ->has('contacts.data', 2)
            ->where('contacts.data.0.id', $newer->id)
            ->where('contacts.data.1.id', $older->id)
            ->missing('summary')
            ->missing('facets'));
});

it('hides do-not-contact by default and shows them when the toggle is off', function () {
    Contact::factory()->create();
    Contact::factory()->doNotContact()->create();

    $this->actingAs(audienceUser())->get('/audience')
        ->assertInertia(fn (Assert $page) => $page->has('contacts.data', 1));

    $this->actingAs(audienceUser())->get('/audience?filter[hide_dnc]=0')
        ->assertInertia(fn (Assert $page) => $page
            ->has('contacts.data', 2)
            ->where('filters.hide_dnc', false));
});

it('paginates with a cursor, 25 rows per page', function () {
    Contact::factory()->count(30)->create();

    $this->actingAs(audienceUser())->get('/audience')
        ->assertInertia(fn (Assert $page) => $page
            ->has('contacts.data', 25)
            ->whereNot('contacts.meta.next_cursor', null)
            ->where('contacts.meta.prev_cursor', null));
});

it('masks phones and emails for users without contacts.view_unmasked', function () {
    Contact::factory()->create(['mobile_e164' => '+971501234567', 'email' => 'ahmed@example.com']);

    $this->actingAs(audienceUser(Role::Viewer))->get('/audience')
        ->assertInertia(fn (Assert $page) => $page
            ->where('contacts.data.0.mobile', '+971 50 ••• ••67')
            ->where('contacts.data.0.email', 'ah•••@example.com'));
});

it('shows full phones and emails to users with contacts.view_unmasked', function () {
    Contact::factory()->create(['mobile_e164' => '+971501234567', 'email' => 'ahmed@example.com']);

    $this->actingAs(audienceUser(Role::Admin))->get('/audience')
        ->assertInertia(fn (Assert $page) => $page
            ->where('contacts.data.0.mobile', '+971501234567')
            ->where('contacts.data.0.email', 'ahmed@example.com'));
});

it('loads the summary and channel counts as a deferred prop', function () {
    Contact::factory()->create(['region_id' => $this->dubai->id, 'mobile_e164' => '+971501111111', 'email' => 'a@example.com']);
    Contact::factory()->create(['region_id' => $this->dubai->id, 'mobile_e164' => '+966501111111', 'email' => null]);
    Contact::factory()->doNotContact()->create(['region_id' => $this->dubai->id]);

    $this->actingAs(audienceUser())
        ->get('/audience?filter[region]='.$this->dubai->id.'&filter[hide_dnc]=0')
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('summary', fn (Assert $reload) => $reload
                ->where('summary.total', 3)
                ->where('summary.uae_mobile', 2)
                ->where('summary.channels.whatsapp', 2)
                ->where('summary.channels.sms', 1)
                ->where('summary.database.contacts', 3)));
});

it('loads facet counts as a deferred prop, counting each facet without its own selection', function () {
    Contact::factory()->count(2)->create(['region_id' => $this->dubai->id, 'gender' => Gender::Female]);
    Contact::factory()->create(['region_id' => $this->sharjah->id, 'gender' => Gender::Female]);
    Contact::factory()->create(['region_id' => $this->dubai->id, 'gender' => Gender::Male]);

    $this->actingAs(audienceUser())
        ->get('/audience?filter[region]='.$this->dubai->id.'&filter[gender]=2')
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('facets', fn (Assert $reload) => $reload
                ->where('facets.facets.region.'.$this->dubai->id, 2)
                ->where('facets.facets.region.'.$this->sharjah->id, 1)
                ->where('facets.facets.gender.2', 2)
                ->where('facets.facets.gender.1', 1)
                ->where('facets.charts.region.'.$this->dubai->id, 2)
                ->missing('facets.charts.region.'.$this->sharjah->id)));
});

it('reads unfiltered facet counts from the materialized view', function () {
    Contact::factory()->count(3)->create(['region_id' => $this->dubai->id]);
    app(RefreshFacetCountsAction::class)->handle();
    Contact::factory()->create(['region_id' => $this->dubai->id]);

    $this->actingAs(audienceUser())->get('/audience')
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('facets', fn (Assert $reload) => $reload
                ->where('facets.facets.region.'.$this->dubai->id, 3)));
});

it('lists only facet options that exist in the data, plus selected ones', function () {
    Contact::factory()->create(['region_id' => $this->dubai->id]);
    app(RefreshFacetCountsAction::class)->handle();

    $this->actingAs(audienceUser())->get('/audience?filter[region]='.$this->sharjah->id)
        ->assertInertia(fn (Assert $page) => $page
            ->where('options.facets.region', fn ($options) => collect($options)->pluck('id')->sort()->values()->all() === [$this->dubai->id, $this->sharjah->id]));
});

it('rejects malformed filter values', function () {
    $this->actingAs(audienceUser())
        ->get('/audience?filter[region]=abc')
        ->assertSessionHasErrors('filter.region');
});

it('forbids users without contacts.view', function () {
    $this->actingAs(User::factory()->create())->get('/audience')->assertForbidden();
});

it('flags contacts with problems and marks the rest clean', function () {
    Contact::factory()->create(['is_junk' => true, 'email_status' => EmailStatus::Invalid]);
    Contact::factory()->create();

    $this->actingAs(audienceUser())->get('/audience')
        ->assertInertia(fn (Assert $page) => $page
            ->where('contacts.data.0.flags', [['label' => 'Clean', 'tone' => 'ok']])
            ->where('contacts.data.1.flags.0.label', 'Junk name')
            ->where('contacts.data.1.flags.1.label', 'Bad email'));
});
