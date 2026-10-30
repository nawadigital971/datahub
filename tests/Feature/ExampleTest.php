<?php

test('returns a successful response for the login page', function () {
    $this->get(route('login'))->assertOk();
});
