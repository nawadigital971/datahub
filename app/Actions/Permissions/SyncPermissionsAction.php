<?php

namespace App\Actions\Permissions;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissionsAction
{
    public function __construct(private PermissionRegistrar $registrar) {}

    /**
     * Make the database match the Permission enum and seed any missing roles.
     *
     * @return array{created: list<string>, deleted: list<string>, roles_created: list<string>}
     */
    public function handle(): array
    {
        $guard = config('auth.defaults.guard');

        $result = DB::transaction(function () use ($guard): array {
            $existing = PermissionModel::query()->where('guard_name', $guard)->pluck('name')->all();

            $created = array_values(array_diff(Permission::values(), $existing));
            foreach ($created as $name) {
                PermissionModel::query()->create(['name' => $name, 'guard_name' => $guard]);
            }

            $deleted = array_values(array_diff($existing, Permission::values()));
            PermissionModel::query()->where('guard_name', $guard)->whereIn('name', $deleted)->delete();

            $this->registrar->forgetCachedPermissions();

            $rolesCreated = [];
            foreach (Role::cases() as $role) {
                $model = RoleModel::query()->firstOrCreate(['name' => $role->value, 'guard_name' => $guard]);

                if ($model->wasRecentlyCreated) {
                    $rolesCreated[] = $role->value;
                    $model->syncPermissions(array_map(fn (Permission $permission): string => $permission->value, $role->defaultPermissions()));
                } elseif ($role === Role::Admin) {
                    $model->syncPermissions(Permission::values());
                }
            }

            return ['created' => $created, 'deleted' => $deleted, 'roles_created' => $rolesCreated];
        });

        $this->registrar->forgetCachedPermissions();

        return $result;
    }
}
