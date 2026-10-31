<?php

namespace App\Console\Commands;

use App\Actions\Users\CreateAdminUserAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('app:create-admin {email} {--name=Admin : Name shown in the dashboard}')]
#[Description('Create the first admin (asks for the password), or make an existing user an admin with a new password')]
class CreateAdminCommand extends Command
{
    public function handle(CreateAdminUserAction $action): int
    {
        $email = (string) $this->argument('email');
        $password = (string) $this->secret('Password (at least 12 characters)');
        $confirmation = (string) $this->secret('Repeat the password');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password, 'password_confirmation' => $confirmation],
            ['email' => ['required', 'email'], 'password' => ['required', 'string', 'min:12', 'confirmed']],
        );
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = $action->handle((string) $this->option('name'), $email, $password);
        $this->components->info("{$user->email} is an admin. Sign in at ".url('/login'));

        return self::SUCCESS;
    }
}
