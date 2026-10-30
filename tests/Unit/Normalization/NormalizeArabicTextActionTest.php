<?php

use App\Actions\Normalization\NormalizeArabicTextAction;

it('normalizes Arabic letter variants, diacritics and tatweel like the database search does', function (string $input, string $expected) {
    expect((new NormalizeArabicTextAction)->handle($input))->toBe($expected);
})->with([
    ['أَحْمَد', 'احمد'],
    ['إبراهيم', 'ابراهيم'],
    ['آل مكتوم', 'ال مكتوم'],
    ['فاطمة', 'فاطمه'],
    ['مصطفى', 'مصطفي'],
    ['محـــمد', 'محمد'],
    ['  José   ALI ', 'jose ali'],
]);
