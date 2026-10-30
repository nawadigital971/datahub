<?php

use App\Actions\Permissions\SyncPermissionsAction;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Export;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    app(SyncPermissionsAction::class)->handle();
    $this->admin = User::factory()->create()->assignRole(Role::Admin->value);
});

it('adds a user with a role and emails them a link to set their password', function () {
    Notification::fake();

    $this->actingAs($this->admin)->post('/admin/users', ['name' => 'Rana Khalil', 'email' => 'rana@example.com', 'role' => 'Marketer'])
        ->assertRedirect();

    $user = User::query()->where('email', 'rana@example.com')->sole();
    expect($user->hasRole('Marketer'))->toBeTrue();
    Notification::assertSentTo($user, ResetPassword::class);
});

it('changes a user\'s role', function () {
    $user = User::factory()->create()->assignRole('Viewer');

    $this->actingAs($this->admin)->put("/admin/users/{$user->id}", ['name' => $user->name, 'email' => $user->email, 'role' => 'Data Editor'])
        ->assertRedirect();

    expect($user->fresh()->getRoleNames()->all())->toBe(['Data Editor']);
});

it('keeps at least one active user who can manage users', function () {
    $this->actingAs($this->admin)->put("/admin/users/{$this->admin->id}", ['name' => 'Me', 'email' => $this->admin->email, 'role' => 'Viewer'])
        ->assertSessionHasErrors(['role' => 'At least one active user must be able to manage users.']);

    expect($this->admin->fresh()->hasRole('Admin'))->toBeTrue();
});

it('deactivates a user: no sign-in, no MCP tokens, history kept', function () {
    $user = User::factory()->create()->assignRole('Marketer');
    $user->createToken('Claude', ['mcp']);
    Export::factory()->for($user)->create();

    $this->actingAs($this->admin)->post("/admin/users/{$user->id}/deactivate")->assertRedirect();

    expect($user->fresh()->isActive())->toBeFalse()
        ->and($user->tokens()->count())->toBe(0)
        ->and(Export::query()->where('user_id', $user->id)->exists())->toBeTrue();

    auth()->logout();
    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'This account has been deactivated. Ask an administrator.']);
    $this->assertGuest();
});

it('signs out a user deactivated while signed in', function () {
    $user = User::factory()->create()->assignRole('Marketer');
    $user->forceFill(['deactivated_at' => now()])->save();

    $this->actingAs($user)->get('/audience')->assertRedirect('/login');
    $this->assertGuest();
});

it('rejects MCP tokens of a deactivated user', function () {
    $user = User::factory()->create()->assignRole('Admin');
    $token = $user->createToken('Claude', ['mcp'])->plainTextToken;
    $user->forceFill(['deactivated_at' => now()])->save();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertUnauthorized();
});

it('does not let admins deactivate themselves', function () {
    $this->actingAs($this->admin)->post("/admin/users/{$this->admin->id}/deactivate")->assertSessionHasErrors('user');
});

it('reactivates a user', function () {
    $user = User::factory()->create(['deactivated_at' => now()]);

    $this->actingAs($this->admin)->post("/admin/users/{$user->id}/reactivate")->assertRedirect();

    expect($user->fresh()->isActive())->toBeTrue();
});

it('issues an MCP token shown once and revokes it', function () {
    $user = User::factory()->create()->assignRole('Data Editor');

    $this->actingAs($this->admin)->post("/admin/users/{$user->id}/tokens", ['name' => 'Claude', 'days' => 30])
        ->assertRedirect()
        ->assertInertiaFlash('mcp_token.user', $user->name);

    $token = $user->tokens()->sole();
    expect($token->abilities)->toBe(['mcp']);

    $this->actingAs($this->admin)->delete("/admin/users/{$user->id}/tokens/{$token->id}")->assertRedirect();
    expect($user->tokens()->count())->toBe(0);
});

it('edits role permissions but never the Admin role', function () {
    $viewer = RoleModel::findByName('Viewer');

    $this->actingAs($this->admin)->put("/admin/roles/{$viewer->id}", ['permissions' => ['contacts.view', 'exports.view_log']])->assertRedirect();
    expect($viewer->fresh()->permissions->pluck('name')->sort()->values()->all())->toBe(['contacts.view', 'exports.view_log']);

    $admin = RoleModel::findByName('Admin');
    $this->actingAs($this->admin)->put("/admin/roles/{$admin->id}", ['permissions' => []])->assertForbidden();
});

it('adds a new role', function () {
    $this->actingAs($this->admin)->post('/admin/roles', ['name' => 'Call centre', 'permissions' => ['contacts.view', 'exports.create']])->assertRedirect();

    expect(RoleModel::findByName('Call centre')->hasPermissionTo(Permission::ExportsCreate->value))->toBeTrue();
});

it('is only for users who can manage users', function () {
    $this->actingAs(User::factory()->create()->assignRole('Marketer'))->get('/admin/users')->assertForbidden();
});

it('lists users with their roles and MCP tokens', function () {
    $this->admin->createToken('Claude', ['mcp']);

    $this->actingAs($this->admin)->get('/admin/users')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/users/Index')
            ->where('users.data.0.role', 'Admin')
            ->has('users.data.0.tokens', 1)
            ->has('roles', 4));
});
