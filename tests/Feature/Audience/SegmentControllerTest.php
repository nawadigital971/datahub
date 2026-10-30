<?php

use App\Actions\Permissions\SyncPermissionsAction;
use App\Enums\Role;
use App\Models\Segment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    app(SyncPermissionsAction::class)->handle();
});

function segmentUser(Role $role = Role::Marketer): User
{
    return User::factory()->create()->assignRole($role->value);
}

it('saves the current filters as a segment, keeping only non-default values', function () {
    $user = segmentUser();

    $this->actingAs($user)
        ->from('/audience')
        ->post('/segments', [
            'name' => 'Dubai mobiles',
            'is_shared' => true,
            'filters' => ['region' => '3,1', 'has_mobile' => '1', 'hide_dnc' => '1'],
        ])
        ->assertRedirect('/audience');

    $segment = Segment::query()->sole();
    expect($segment->name)->toBe('Dubai mobiles')
        ->and($segment->user_id)->toBe($user->id)
        ->and($segment->is_shared)->toBeTrue()
        ->and($segment->filters)->toEqual(['has_mobile' => '1', 'region' => '1,3']);
});

it('requires a name', function () {
    $this->actingAs(segmentUser())
        ->post('/segments', ['name' => '', 'filters' => []])
        ->assertSessionHasErrors(['name' => 'The name field is required.']);
});

it('forbids saving without segments.manage', function () {
    $this->actingAs(segmentUser(Role::Viewer))
        ->post('/segments', ['name' => 'Nope', 'filters' => []])
        ->assertForbidden();
});

it('shows own segments and segments shared by others', function () {
    $user = segmentUser();
    Segment::factory()->for($user)->create(['name' => 'Mine']);
    Segment::factory()->create(['name' => 'Team list', 'is_shared' => true]);
    Segment::factory()->create(['name' => 'Private to someone else']);

    $this->actingAs($user)->get('/audience')
        ->assertInertia(fn (Assert $page) => $page
            ->where('segments', fn ($saved) => collect($saved)->pluck('name')->sort()->values()->all() === ['Mine', 'Team list']));
});

it('lets the owner delete a segment', function () {
    $user = segmentUser();
    $segment = Segment::factory()->for($user)->create();

    $this->actingAs($user)->delete("/segments/{$segment->id}")->assertRedirect();

    expect(Segment::query()->count())->toBe(0);
});

it('does not let anyone else delete a shared segment', function () {
    $segment = Segment::factory()->create(['is_shared' => true]);

    $this->actingAs(segmentUser())->delete("/segments/{$segment->id}")->assertForbidden();

    expect(Segment::query()->count())->toBe(1);
});
