<?php

namespace App\Console\Commands;

use App\Actions\Mcp\IssueMcpTokenAction;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:revoke-mcp-tokens {email}')]
#[Description('Revoke every MCP token of a user')]
class RevokeMcpTokensCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();
        if ($user === null) {
            $this->components->error('No user with that email.');

            return self::FAILURE;
        }

        $revoked = $user->tokens()->whereJsonContains('abilities', IssueMcpTokenAction::ABILITY)->delete();
        $this->components->info("Revoked {$revoked} MCP tokens.");

        return self::SUCCESS;
    }
}
