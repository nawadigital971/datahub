<?php

namespace App\Actions\Users;

use App\Models\User;

class RevokeMcpTokenAction
{
    public function handle(User $user, int $tokenId): bool
    {
        return $user->tokens()->whereKey($tokenId)->delete() > 0;
    }
}
