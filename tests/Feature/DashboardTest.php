<?php

use App\Models\User;

test('guests are redirected from the home page to the login page', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('authenticated users are sent from the home page to the audience', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect('/audience');
});
