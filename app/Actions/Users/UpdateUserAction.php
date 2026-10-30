<?php

namespace App\Actions\Users;

use App\DTOs\UserData;
use App\Models\User;

class UpdateUserAction
{
    public function __construct(private EnsureUserManagerRemainsAction $ensureManager) {}

    public function handle(User $user, UserData $data): User
    {
        $this->ensureManager->handle($user, $data->role);

        $user->update(['name' => $data->name, 'email' => $data->email]);
        $user->syncRoles([$data->role]);

        return $user;
    }
}
