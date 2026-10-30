<?php

use App\Actions\Normalization\NormalizeEmailAction;

beforeEach(function () {
    $this->artisan('app:import-cleaning-rules');
});

it('lowercases, trims and strips mailto and punctuation', function () {
    $result = app(NormalizeEmailAction::class)->handle(' mailto:Ahmed.Ali@Example.COM; ')[0];

    expect($result->email)->toBe('ahmed.ali@example.com')
        ->and($result->valid)->toBeTrue()
        ->and($result->suggestion)->toBeNull();
});

it('suggests fixes for common domain typos without applying them', function (string $raw, string $suggestion) {
    $result = app(NormalizeEmailAction::class)->handle($raw)[0];

    expect($result->email)->toBe(strtolower($raw))
        ->and($result->suggestion)->toBe($suggestion);
})->with([
    ['sara@gamil.com', 'sara@gmail.com'],
    ['omar@hotmial.com', 'omar@hotmail.com'],
    ['info@yahoo.co', 'info@yahoo.com'],
    ['agent@company.comm', 'agent@company.com'],
]);

it('splits several addresses in one cell', function () {
    $results = app(NormalizeEmailAction::class)->handle('a@example.com, b@example.com / c@example.com');

    expect(array_map(fn ($r) => $r->email, $results))->toBe(['a@example.com', 'b@example.com', 'c@example.com']);
});

it('recognizes web addresses in an "Email/ Website" column', function () {
    $result = app(NormalizeEmailAction::class)->handle('www.cedartree.ae')[0];

    expect($result->email)->toBeNull()
        ->and($result->website)->toBe('www.cedartree.ae');
});

it('marks malformed addresses invalid', function () {
    $result = app(NormalizeEmailAction::class)->handle('ahmed@@gmail')[0];

    expect($result->valid)->toBeFalse();
});
