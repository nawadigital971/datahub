<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates the first admin with the typed password', function () {
    $this->artisan('app:create-admin', ['email' => 'Owner@Example.com', '--name' => 'Owner'])
        ->expectsQuestion('Password (at least 12 characters)', 'a-long-secret-1')
        ->expectsQuestion('Repeat the password', 'a-long-secret-1')
        ->assertSuccessful();

    $user = User::query()->where('email', 'owner@example.com')->sole();
    expect($user->hasRole(Role::Admin->value))->toBeTrue()
        ->and($user->can('settings.update'))->toBeTrue()
        ->and(Hash::check('a-long-secret-1', $user->password))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();
});

it('refuses short or mismatched passwords', function () {
    $this->artisan('app:create-admin', ['email' => 'owner@example.com'])
        ->expectsQuestion('Password (at least 12 characters)', 'short')
        ->expectsQuestion('Repeat the password', 'other')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});
