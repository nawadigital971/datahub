<?php

use App\Actions\Contacts\BustAudienceCacheAction;
use App\Actions\Normalization\ParseNotesAction;
use App\Enums\CallOutcome;
use App\Enums\CleaningRuleType;
use App\Models\CleaningRule;

beforeEach(function () {
    $this->artisan('app:import-cleaning-rules');
});

it('reads call-centre codes, the last one winning', function (string $notes, CallOutcome $outcome) {
    expect(app(ParseNotesAction::class)->handle($notes)->callOutcome)->toBe($outcome);
})->with([
    ['NI', CallOutcome::NotInterested],
    ['CB 3pm', CallOutcome::CallBack],
    ['SW', CallOutcome::SwitchedOff],
    ['D', CallOutcome::WrongNumber],
    ['CB tuesday / NI', CallOutcome::NotInterested],
    ['customer said not interested', CallOutcome::NotInterested],
]);

it('does not read call codes inside words', function () {
    expect(app(ParseNotesAction::class)->handle('Dubai Investments, Deira')->callOutcome)->toBeNull();
});

it('extracts landlines, other numbers, emails, contract and agent', function () {
    $result = app(ParseNotesAction::class)->handle('Landline: 04-4473955, contract no. AB-1234, agent: Rana Khalil, alt 050 765 4321 sara@example.com');

    expect($result->landlines)->toBe(['04-4473955'])
        ->and($result->phones)->toBe(['050 765 4321'])
        ->and($result->emails)->toBe(['sara@example.com'])
        ->and($result->contractNo)->toBe('AB-1234')
        ->and($result->agent)->toBe('Rana Khalil');
});

it('spots the verify-transliteration tag', function () {
    expect(app(ParseNotesAction::class)->handle('Verify transliteration')->verifyTransliteration)->toBeTrue();
});

it('reads labels added to the cleaning rules', function () {
    expect(app(ParseNotesAction::class)->handle('Office: 04-4473955, Ref AB-77')->landlines)->toBe([]);

    CleaningRule::query()->create(['type' => CleaningRuleType::NoteLabel, 'key' => 'office', 'value' => 'landline']);
    CleaningRule::query()->create(['type' => CleaningRuleType::NoteLabel, 'key' => 'ref', 'value' => 'contract']);
    app(BustAudienceCacheAction::class)->handle();
    app()->forgetScopedInstances();

    $result = app(ParseNotesAction::class)->handle('Office: 04-4473955, Ref AB-77');
    expect($result->landlines)->toBe(['04-4473955'])
        ->and($result->contractNo)->toBe('AB-77');
});

it('does not read a label inside another word', function () {
    expect(app(ParseNotesAction::class)->handle('Hotel: 04-4473955')->landlines)->toBe([]);
});
