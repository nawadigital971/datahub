<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class SetUserActiveAction
{
    public function __construct(private EnsureUserManagerRemainsAction $ensureManager) {}

    /**
     * Deactivate (sign-in blocked, MCP tokens revoked; history kept) or reactivate a user.
     */
    public function handle(User $user, bool $active, User $actingUser): User
    {
        if (! $active) {
            if ($user->is($actingUser)) {
                throw ValidationException::withMessages(['user' => 'You cannot deactivate your own account.']);
            }
            $this->ensureManager->handle($user, null);
            $user->tokens()->delete();
        }

        $user->forceFill(['deactivated_at' => $active ? null : now()])->save();

        return $user;
    }
}
