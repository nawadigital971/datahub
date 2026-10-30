<?php

use App\Jobs\GenerateExportJob;
use App\Models\Contact;
use App\Models\Export;
use App\Settings\DataSettings;
use Illuminate\Support\Facades\Storage;

it('deletes export files past the retention period and keeps the log entries', function () {
    Storage::fake('local');
    Contact::factory()->create();
    $old = Export::factory()->create(['created_at' => now()->subDays(91)]);
    $recent = Export::factory()->create(['created_at' => now()->subDays(5)]);
    GenerateExportJob::dispatchSync($old->id);
    GenerateExportJob::dispatchSync($recent->id);
    expect(app(DataSettings::class)->export_retention_days)->toBe(90);

    $this->artisan('app:purge-exports')->expectsOutputToContain('Deleted 1 expired export files.')->assertSuccessful();

    expect($old->fresh()->getFirstMedia('file'))->toBeNull()
        ->and($recent->fresh()->getFirstMedia('file'))->not->toBeNull()
        ->and(Export::query()->count())->toBe(2);
});
