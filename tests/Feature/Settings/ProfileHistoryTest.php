<?php

use App\Models\Export;
use App\Models\User;

it('refuses to delete an account that has exports in the log', function () {
    $user = User::factory()->create();
    Export::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrors(['password' => 'Your account has exports or imports in the log, so it cannot be deleted. Ask an administrator to deactivate it instead.']);

    expect($user->fresh())->not->toBeNull();
});
