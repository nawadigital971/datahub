<?php

use App\Enums\EmailStatus;
use App\Enums\ExportChannel;
use App\Enums\ExportStatus;
use App\Jobs\GenerateExportJob;
use App\Models\Contact;
use App\Models\Country;
use App\Models\Export;
use App\Models\Region;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

/**
 * Run an export and return its CSV rows (header first).
 *
 * @return list<list<string>>
 */
function runExport(ExportChannel $channel, bool $hashed = false, array $filters = []): array
{
    $export = Export::factory()->create(['channel' => $channel, 'hashed' => $hashed, 'filters' => $filters]);
    GenerateExportJob::dispatchSync($export->id);

    $contents = file_get_contents($export->fresh()->getFirstMedia('file')->getPath());
    $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);

    return array_map(fn (string $line): array => str_getcsv($line, escape: ''), array_values(array_filter(explode("\n", $contents))));
}

it('writes the file to the private disk and records the row count', function () {
    Contact::factory()->count(3)->create();
    $export = Export::factory()->create(['channel' => ExportChannel::Email]);

    GenerateExportJob::dispatchSync($export->id);

    $export->refresh();
    expect($export->status)->toBe(ExportStatus::Done)
        ->and($export->row_count)->toBe(3)
        ->and($export->getFirstMedia('file')->disk)->toBe('local');
});

it('never exports anyone on the do-not-contact list, even when the audience showed them', function () {
    Contact::factory()->create(['email' => 'keep@example.com']);
    Contact::factory()->doNotContact()->create(['email' => 'blocked@example.com']);

    $rows = runExport(ExportChannel::Email, filters: ['hide_dnc' => '0']);

    expect(array_column(array_slice($rows, 1), 0))->toBe(['keep@example.com']);
});

it('only includes contacts the channel can reach', function () {
    Contact::factory()->create(['mobile_e164' => '+971501111111']);
    Contact::factory()->create(['mobile_e164' => '+966501111111']);
    Contact::factory()->create(['email' => 'invalid@example.com', 'email_status' => EmailStatus::Invalid, 'mobile_e164' => null]);

    expect(runExport(ExportChannel::Sms))->toHaveCount(2)
        ->and(runExport(ExportChannel::Whatsapp))->toHaveCount(3)
        ->and(runExport(ExportChannel::Email))->toHaveCount(3);
});

it('writes the email platform columns with names split and a byte-order mark for Excel', function () {
    $dubai = Region::factory()->create(['name' => ['en' => 'Dubai']]);
    Contact::factory()->create(['name_en' => 'Fatima Al Mansoori', 'email' => 'fatima@example.com', 'region_id' => $dubai->id]);
    $export = Export::factory()->create(['channel' => ExportChannel::Email]);

    GenerateExportJob::dispatchSync($export->id);

    $contents = file_get_contents($export->fresh()->getFirstMedia('file')->getPath());
    expect($contents)->toStartWith("\xEF\xBB\xBFemail,first_name,last_name,emirate,source\n")
        ->and($contents)->toContain('fatima@example.com,Fatima,"Al Mansoori",Dubai,');
});

it('hashes Meta identifiers after normalizing them the way Meta asks', function () {
    $uae = Country::factory()->create(['iso2' => 'AE']);
    $dubai = Region::factory()->create(['name' => ['en' => 'Dubai']]);
    Contact::factory()->create([
        'name_en' => 'Fatima Al-Mansoori',
        'email' => ' Fatima@Example.com',
        'mobile_e164' => '+971501234567',
        'region_id' => $dubai->id,
        'country_id' => $uae->id,
    ]);

    [$header, $row] = runExport(ExportChannel::Meta, hashed: true);

    expect($header)->toBe(['email', 'phone', 'fn', 'ln', 'ct', 'country'])
        ->and($row)->toBe([
            hash('sha256', 'fatima@example.com'),
            hash('sha256', '971501234567'),
            hash('sha256', 'fatima'),
            hash('sha256', 'almansoori'),
            hash('sha256', 'dubai'),
            hash('sha256', 'ae'),
        ]);
});

it('hashes Google identifiers with the phone in E.164 and keeps the country readable', function () {
    $uae = Country::factory()->create(['iso2' => 'AE']);
    Contact::factory()->create(['name_en' => 'Omar Saleh', 'email' => 'omar@example.com', 'mobile_e164' => '+971501234567', 'country_id' => $uae->id]);

    [, $row] = runExport(ExportChannel::Google, hashed: true);

    expect($row)->toBe([
        hash('sha256', 'omar@example.com'),
        hash('sha256', '+971501234567'),
        hash('sha256', 'omar'),
        hash('sha256', 'saleh'),
        'AE',
    ]);
});

it('neutralizes spreadsheet formulas in imported names', function () {
    Contact::factory()->create(['name_en' => '=HYPERLINK("http://evil")', 'mobile_e164' => '+971501234567']);

    [, $row] = runExport(ExportChannel::Calls);

    expect($row[0])->toBe("'=HYPERLINK(\"http://evil\")");
});

it('marks the export failed when the job fails', function () {
    $export = Export::factory()->create(['status' => ExportStatus::Running]);

    (new GenerateExportJob($export->id))->failed(new RuntimeException('boom'));

    expect($export->fresh()->status)->toBe(ExportStatus::Failed);
});
