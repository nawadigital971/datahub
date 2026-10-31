<?php

namespace App\Console\Commands;

use App\Actions\Mcp\IssueMcpTokenAction;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:issue-mcp-token {email : The user Claude will act as} {--name=Claude : Label for the token} {--days=90 : Days until the token expires}')]
#[Description('Create an MCP token for a user (Claude then acts with that user\'s permissions)')]
class IssueMcpTokenCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(IssueMcpTokenAction $action): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();
        if ($user === null) {
            $this->components->error('No user with that email.');

            return self::FAILURE;
        }

        $days = max(1, (int) $this->option('days'));
        $token = $action->handle($user, (string) $this->option('name'), $days);

        $this->components->info("Token for {$user->name}, valid {$days} days. It is shown only once:");
        $this->line($token->plainTextToken);
        $this->newLine();
        $this->line('MCP URL (keep it secret, it contains the token):');
        $this->line(url('/mcp').'?token='.urlencode($token->plainTextToken));
        $this->newLine();
        $this->line('Or use '.url('/mcp').' with the header "Authorization: Bearer <token>".');

        return self::SUCCESS;
    }
}
