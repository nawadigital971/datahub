<?php

namespace App\Actions\Users;

use App\DTOs\UserData;
use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class CreateUserAction
{
    /**
     * Add a team member with a role and email them a link to choose their password.
     */
    public function handle(UserData $data): User
    {
        $user = User::query()->create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => Str::password(32),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->syncRoles([$data->role]);

        Password::broker()->sendResetLink(['email' => $user->email]);

        return $user;
    }
}
