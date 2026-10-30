<?php

namespace App\Actions\Mcp;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

class IssueMcpTokenAction
{
    public const string ABILITY = 'mcp';

    /**
     * A personal access token that only works for the MCP server, expiring after $days.
     */
    public function handle(User $user, string $name, int $days): NewAccessToken
    {
        return $user->createToken($name, [self::ABILITY], now()->addDays($days));
    }
}
