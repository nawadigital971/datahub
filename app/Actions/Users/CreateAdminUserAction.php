<?php

namespace App\Actions\Users;

use App\Actions\Permissions\SyncPermissionsAction;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateAdminUserAction
{
    public function __construct(private SyncPermissionsAction $syncPermissions) {}

    /**
     * Create the first admin on a new server (or make an existing user an admin with a new password).
     */
    public function handle(string $name, string $email, string $password): User
    {
        $this->syncPermissions->handle();

        $user = User::query()->firstOrNew(['email' => mb_strtolower(trim($email))]);
        $user->forceFill([
            'name' => $user->exists ? $user->name : $name,
            'password' => Hash::make($password),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();
        $user->assignRole(Role::Admin->value);

        return $user;
    }
}
