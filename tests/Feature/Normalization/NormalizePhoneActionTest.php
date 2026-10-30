<?php

use App\Actions\Contacts\BustAudienceCacheAction;
use App\Actions\Normalization\NormalizePhoneAction;
use App\Enums\CleaningRuleType;
use App\Enums\PhoneType;
use App\Models\CleaningRule;

beforeEach(function () {
    $this->artisan('app:import-cleaning-rules');
});

dataset('phones from the files', [
    'UAE mobile stored without +' => ['971501234567', '+971501234567', PhoneType::Mobile, 'AE'],
    'local mobile with dashes' => ['050-123 4567', '+971501234567', PhoneType::Mobile, 'AE'],
    'local mobile without 0' => ['501234567', '+971501234567', PhoneType::Mobile, 'AE'],
    'Dubai landline' => ['04-4473955', '+97144473955', PhoneType::Landline, 'AE'],
    'Sharjah landline in brackets' => ['(06) 5551234', '+97165551234', PhoneType::Landline, 'AE'],
    '00 international prefix' => ['00966501234567', '+966501234567', PhoneType::Mobile, 'SA'],
    'Egyptian mobile with +' => ['+20 100 123 4567', '+201001234567', PhoneType::Mobile, 'EG'],
    'Indian mobile without +' => ['919876543210', '+919876543210', PhoneType::Mobile, 'IN'],
    'Arabic-Indic digits' => ['٠٥٠١٢٣٤٥٦٧', '+971501234567', PhoneType::Mobile, 'AE'],
]);

it('normalizes phone shapes found in the source files', function (string $raw, string $e164, PhoneType $type, string $country) {
    $results = app(NormalizePhoneAction::class)->handle($raw);

    expect($results)->toHaveCount(1)
        ->and($results[0]->e164)->toBe($e164)
        ->and($results[0]->type)->toBe($type)
        ->and($results[0]->countryIso)->toBe($country);
})->with('phones from the files');

it('splits several numbers in one cell', function () {
    $results = app(NormalizePhoneAction::class)->handle('050 1234567 / 04 2345678 or +966501234567');

    expect(array_map(fn ($r) => $r->e164, $results))->toBe(['+971501234567', '+97142345678', '+966501234567']);
});

it('keeps invalid numbers as invalid with their raw text', function (string $raw) {
    $results = app(NormalizePhoneAction::class)->handle($raw);

    expect($results[0]->isValid())->toBeFalse()
        ->and($results[0]->raw)->toBe($raw);
})->with(['12345', '0000000000', '9715012345678901']);

it('returns nothing for empty cells', function () {
    expect(app(NormalizePhoneAction::class)->handle('  '))->toBe([])
        ->and(app(NormalizePhoneAction::class)->handle(null))->toBe([]);
});

it('splits numbers on separator words from the cleaning rules', function () {
    expect(app(NormalizePhoneAction::class)->handle('0501234567 او 0559876543'))->toHaveCount(2);

    CleaningRule::query()->create(['type' => CleaningRuleType::PhoneSeparator, 'key' => 'aw']);
    app(BustAudienceCacheAction::class)->handle();
    app()->forgetScopedInstances();

    expect(array_map(fn ($phone) => $phone->e164, app(NormalizePhoneAction::class)->handle('0501234567 aw 0559876543')))
        ->toBe(['+971501234567', '+971559876543']);
});
