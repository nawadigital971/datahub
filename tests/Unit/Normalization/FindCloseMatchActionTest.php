<?php

use App\Actions\Normalization\FindCloseMatchAction;

it('matches small typos of longer words', function (string $needle, ?int $expected) {
    $candidates = ['dubai' => 1, 'sharjah' => 2, 'al barsha' => 3, 'real estate' => 4, 'دبي' => 5];

    expect((new FindCloseMatchAction)->handle($needle, $candidates))->toBe($expected);
})->with([
    'one letter off' => ['dubay', 1],
    'missing letter' => ['sharja', 2],
    'swapped letters in a long word' => ['al barsah', 3],
    'two letters off in a long word' => ['real estat', 4],
    'short words never fuzzy-match' => ['dbi', null],
    'too different' => ['riyadh', null],
]);

it('matches nothing when two items are equally close', function () {
    expect((new FindCloseMatchAction)->handle('marina', ['marine' => 1, 'marino' => 2]))->toBeNull();
});
