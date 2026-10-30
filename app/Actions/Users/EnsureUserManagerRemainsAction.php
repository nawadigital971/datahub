<?php

namespace App\Actions\Users;

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class EnsureUserManagerRemainsAction
{
    /**
     * Refuse a change that would leave no active user able to manage users.
     *
     * @param  string|null  $newRole  The role the user is about to get (null = being deactivated).
     */
    public function handle(User $user, ?string $newRole): void
    {
        $keepsAccess = $newRole !== null
            && (Role::findByName($newRole)->hasPermissionTo(Permission::UsersManage->value));

        if ($keepsAccess || ! $user->can(Permission::UsersManage->value)) {
            return;
        }

        $others = User::permission(Permission::UsersManage->value)
            ->whereKeyNot($user->id)
            ->whereNull('deactivated_at')
            ->exists();

        if (! $others) {
            throw ValidationException::withMessages([
                'role' => 'At least one active user must be able to manage users.',
            ]);
        }
    }
}
