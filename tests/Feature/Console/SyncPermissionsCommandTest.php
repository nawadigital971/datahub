<?php

use App\Enums\Permission;
use App\Enums\Role;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * @return list<string>
 */
function rolePermissionNames(Role $role): array
{
    return RoleModel::findByName($role->value)->permissions->pluck('name')->sort()->values()->all();
}

it('creates every permission from the enum and the four roles', function () {
    $this->artisan('app:sync-permissions')->assertSuccessful();

    expect(PermissionModel::query()->pluck('name')->sort()->values()->all())
        ->toBe(collect(Permission::values())->sort()->values()->all())
        ->and(RoleModel::query()->pluck('name')->sort()->values()->all())
        ->toBe(['Admin', 'Data Editor', 'Marketer', 'Viewer']);
});

it('gives each new role its default permissions', function (Role $role, array $expected) {
    $this->artisan('app:sync-permissions')->assertSuccessful();

    expect(rolePermissionNames($role))->toBe(collect($expected)->sort()->values()->all());
})->with([
    'Admin' => [Role::Admin, Permission::values()],
    'Marketer' => [Role::Marketer, ['contacts.view', 'segments.manage', 'exports.create', 'exports.view_log']],
    'Data Editor' => [Role::DataEditor, ['contacts.view', 'imports.create', 'imports.review']],
    'Viewer' => [Role::Viewer, ['contacts.view']],
]);

it('keeps permissions edited on an existing role when run again', function () {
    $this->artisan('app:sync-permissions')->assertSuccessful();
    RoleModel::findByName(Role::Viewer->value)->givePermissionTo(Permission::ExportsViewLog->value);

    $this->artisan('app:sync-permissions')->assertSuccessful();

    expect(rolePermissionNames(Role::Viewer))->toBe(['contacts.view', 'exports.view_log']);
});

it('restores every permission on the Admin role when run again', function () {
    $this->artisan('app:sync-permissions')->assertSuccessful();
    RoleModel::findByName(Role::Admin->value)->revokePermissionTo(Permission::UsersManage->value);

    $this->artisan('app:sync-permissions')->assertSuccessful();

    expect(rolePermissionNames(Role::Admin))->toBe(collect(Permission::values())->sort()->values()->all());
});

it('deletes permissions that are no longer in the enum', function () {
    PermissionModel::query()->create(['name' => 'contacts.export', 'guard_name' => 'web']);

    $this->artisan('app:sync-permissions')->assertSuccessful();

    expect(PermissionModel::query()->where('name', 'contacts.export')->exists())->toBeFalse();
});

it('does not duplicate anything when run twice', function () {
    $this->artisan('app:sync-permissions')->assertSuccessful();
    $this->artisan('app:sync-permissions')
        ->expectsOutputToContain('0 created, 0 deleted. Roles created: none.')
        ->assertSuccessful();

    expect(PermissionModel::query()->count())->toBe(count(Permission::cases()))
        ->and(RoleModel::query()->count())->toBe(count(Role::cases()));
});
