<?php

namespace App\Actions\Users;

use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SaveRoleAction
{
    public function __construct(private PermissionRegistrar $registrar) {}

    /**
     * Create a role or replace an existing role's permissions.
     *
     * @param  list<string>  $permissions
     */
    public function handle(array $permissions, ?Role $role = null, ?string $name = null): Role
    {
        $role ??= Role::query()->create(['name' => (string) $name, 'guard_name' => config('auth.defaults.guard')]);
        $role->syncPermissions($permissions);
        $this->registrar->forgetCachedPermissions();

        return $role;
    }
}
