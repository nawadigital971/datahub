<?php

use App\Actions\Permissions\SyncPermissionsAction;
use App\Enums\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    app(SyncPermissionsAction::class)->handle();
});

function userWithRole(Role $role): User
{
    return User::factory()->create()->assignRole($role->value);
}

it('redirects guests to the login page', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(['audience.index', 'imports.index', 'exports.index', 'health.index', 'sources.index', 'admin.settings.edit', 'admin.users.index']);

it('lets each role open only the screens its permissions allow', function (Role $role, string $route, int $status) {
    $this->actingAs(userWithRole($role))->get(route($route))->assertStatus($status);
})->with([
    'admin → users' => [Role::Admin, 'admin.users.index', 200],
    'admin → settings' => [Role::Admin, 'admin.settings.edit', 200],
    'marketer → exports' => [Role::Marketer, 'exports.index', 200],
    'marketer → imports' => [Role::Marketer, 'imports.index', 403],
    'data editor → imports' => [Role::DataEditor, 'imports.index', 200],
    'data editor → exports' => [Role::DataEditor, 'exports.index', 403],
    'viewer → audience' => [Role::Viewer, 'audience.index', 200],
    'viewer → health' => [Role::Viewer, 'health.index', 200],
    'viewer → sources' => [Role::Viewer, 'sources.index', 200],
    'viewer → settings' => [Role::Viewer, 'admin.settings.edit', 403],
    'viewer → users' => [Role::Viewer, 'admin.users.index', 403],
]);

it('forbids a user without a role from the audience', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('audience.index'))
        ->assertForbidden();
});

it('shares the site name and the user\'s permissions with the page', function () {
    $this->actingAs(userWithRole(Role::DataEditor))
        ->get(route('audience.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('audience/Index')
            ->where('name', 'NAWAA Data Hub')
            ->where('auth.permissions', fn ($permissions) => collect($permissions)->sort()->values()->all() === ['contacts.view', 'imports.create', 'imports.review'])
        );
});
