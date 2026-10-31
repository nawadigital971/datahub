<?php

test('every response tells crawlers not to index the app', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet, noimageindex')
        ->assertSee('<meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">', false);
});

test('error responses also carry the noindex header', function () {
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet, noimageindex');
});
