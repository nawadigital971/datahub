<?php

use App\Actions\Contacts\BustAudienceCacheAction;
use App\Actions\Imports\AnalyzeRowAction;
use App\DTOs\Imports\AnalysisContext;
use App\DTOs\Imports\AnalyzedRow;
use App\DTOs\Imports\MappedRow;
use App\Enums\CallOutcome;
use App\Enums\CleaningRuleType;
use App\Enums\ContactType;
use App\Enums\Gender;
use App\Enums\IssueCode;
use App\Enums\RowSeverity;
use App\Models\Area;
use App\Models\CleaningRule;
use App\Models\Region;

beforeEach(function () {
    $this->artisan('app:import-all');
});

function analyze(array $values, ?int $sourceYear = 2024): AnalyzedRow
{
    return app(AnalyzeRowAction::class)->handle(new MappedRow(...$values), new AnalysisContext($sourceYear, 2018));
}

it('turns a clean master-sheet row into parsed columns', function () {
    $row = analyze([
        'nameEn' => 'Fatima Al Mansoori', 'nameAr' => 'فاطمة المنصوري', 'gender' => 'F',
        'phones' => ['971501234567'], 'emails' => ['Fatima@Example.com'], 'address' => 'International City, Dubai', 'seenYear' => '2023-05-01',
    ]);

    expect($row->mobile)->toBe('+971501234567')
        ->and($row->email)->toBe('fatima@example.com')
        ->and($row->regionId)->toBe(Region::query()->where('slug', 'dubai')->value('id'))
        ->and($row->areaId)->toBe(Area::query()->where('slug', 'international-city')->value('id'))
        ->and($row->seenYear)->toBe(2023)
        ->and($row->issues)->toBe([])
        ->and($row->severity())->toBe(RowSeverity::Clean);
});

it('finds the district from an Arabic address', function () {
    expect(analyze(['phones' => ['0501234567'], 'address' => 'قريب سفير مول - المدينه العالميه'])->areaId)
        ->toBe(Area::query()->where('slug', 'international-city')->value('id'));
});

it('moves a landline out of the mobile column', function () {
    $row = analyze(['phones' => ['04-4473955']]);

    expect($row->mobile)->toBeNull()
        ->and($row->landline)->toBe('+97144473955')
        ->and($row->has(IssueCode::LandlineInMobile))->toBeTrue();
});

it('flags rows nobody can be reached on as errors', function () {
    $row = analyze(['nameEn' => 'Omar Saleh']);

    expect($row->has(IssueCode::MissingContactMethod))->toBeTrue()
        ->and($row->severity())->toBe(RowSeverity::Error);
});

it('flags invalid phones and emails and suggests email typo fixes', function () {
    $row = analyze(['phones' => ['12345', '0501234567'], 'emails' => ['sara@gamil.com']]);

    expect($row->has(IssueCode::InvalidPhone))->toBeTrue()
        ->and($row->mobile)->toBe('+971501234567')
        ->and($row->email)->toBe('sara@gamil.com')
        ->and(collect($row->issues)->firstWhere('code', IssueCode::EmailTypo)->suggestion)->toBe('sara@gmail.com');
});

it('flags junk names, machine transliterations and suspicious genders', function () {
    expect(analyze(['nameEn' => 'I Phone 11', 'phones' => ['0501234567']])->has(IssueCode::JunkName))->toBeTrue()
        ->and(analyze(['nameEn' => 'Mhnd Nsryn', 'nameAr' => 'مهند نسرين', 'phones' => ['0501234567']])->has(IssueCode::UnverifiedTransliteration))->toBeTrue()
        ->and(analyze(['nameEn' => 'Fatima Saleh', 'gender' => 'Male', 'phones' => ['0501234567']])->has(IssueCode::GenderSuspect))->toBeTrue();
});

it('reads call outcomes and extra numbers from the notes', function () {
    $row = analyze(['phones' => ['0501234567'], 'notes' => ['NI - Landline: 04-4473955 alt 055 765 4321']]);

    expect($row->callOutcome)->toBe(CallOutcome::NotInterested)
        ->and($row->landline)->toBe('+97144473955')
        ->and(collect($row->issues)->firstWhere('code', IssueCode::PhoneInNotes)->suggestion)->toBe('+971557654321');
});

it('detects the language of a "Name" column and treats company-only rows as businesses', function () {
    $person = analyze(['names' => ['محمد علي'], 'phones' => ['0501234567']]);
    $business = analyze(['company' => 'Cedar Tree Restaurant', 'landlines' => ['04 2345678']]);

    expect($person->nameAr)->toBe('محمد علي')
        ->and($person->nameEn)->toBeNull()
        ->and($business->type)->toBe(ContactType::Business);
});

it('flags stale data and unknown places', function () {
    $row = analyze(['phones' => ['0501234567'], 'address' => 'Somewhere far away'], sourceYear: 2013);

    expect($row->has(IssueCode::StaleData))->toBeTrue()
        ->and($row->has(IssueCode::UnknownRegion))->toBeTrue();
});

it('reads gender and record type from words in the cleaning rules', function () {
    expect(analyze(['nameEn' => 'Huda Saleh', 'gender' => 'Mrs.', 'phones' => ['0501234567']])->gender)->toBe(Gender::Female)
        ->and(analyze(['nameAr' => 'سالم', 'gender' => 'ذكر', 'phones' => ['0501234567']])->gender)->toBe(Gender::Male)
        ->and(analyze(['nameEn' => 'Huda Saleh', 'gender' => 'Sayyida', 'phones' => ['0501234567']])->gender)->toBeNull()
        ->and(analyze(['company' => 'Noor', 'type' => 'شركة ذات مسؤولية', 'phones' => ['0501234567']])->type)->toBe(ContactType::Business);

    CleaningRule::query()->create(['type' => CleaningRuleType::GenderWord, 'key' => 'Sayyida', 'value' => 'f']);
    CleaningRule::query()->create(['type' => CleaningRuleType::BusinessWord, 'key' => 'est.']);
    app(BustAudienceCacheAction::class)->handle();
    app()->forgetScopedInstances();

    expect(analyze(['nameEn' => 'Huda Saleh', 'gender' => 'sayyida', 'phones' => ['0501234567']])->gender)->toBe(Gender::Female)
        ->and(analyze(['company' => 'Noor', 'type' => 'Trading Est.', 'phones' => ['0501234567']])->type)->toBe(ContactType::Business);
});
