<?php

namespace Database\Seeders;

use App\Actions\Contacts\RefreshFacetCountsAction;
use App\Enums\LawfulBasis;
use App\Models\Area;
use App\Models\Country;
use App\Models\Region;
use App\Models\Sector;
use App\Models\Source;
use App\Models\SourceGroup;
use Database\Seeders\Demo\DemoContactGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Pdo\Pgsql;
use RuntimeException;

/**
 * Loads fake demo contacts (default 1,000,000) with COPY for performance testing.
 * Run with: php artisan db:seed --class=DemoContactsSeeder
 */
class DemoContactsSeeder extends Seeder
{
    private const int CHUNK = 20_000;

    /** Demo sources: slug, group slug, English name, Arabic name, collected year. */
    private const array DEMO_SOURCES = [
        ['layalina-subscribers', 'magazines', 'Layalina subscribers', 'مشتركو ليالينا', null],
        ['top-gear-gala-subscribers', 'magazines', 'Top Gear / Gala subscribers', 'مشتركو توب جير / جالا', null],
        ['call-centre-lists-2013', 'call-centre', 'Call centre lists 2013', 'قوائم مركز الاتصال 2013', 2013],
        ['photographers-award-entrants', 'photography', 'Photographers – award entrants', 'المصورون – المشاركون في الجائزة', null],
        ['photographers-uae-community', 'photography', 'Photographers – UAE community', 'المصورون – مجتمع الإمارات', null],
        ['real-estate-agents', 'real-estate', 'Real estate agents', 'الوسطاء العقاريون', null],
        ['waseet-business-directory', 'waseet', 'Waseet business directory', 'دليل الوسيط التجاري', null],
        ['designer-outlet-customers', 'retail', 'Designer Outlet customers', 'عملاء ديزاينر آوتلت', null],
        ['soclean-customers', 'retail', 'SoClean customers', 'عملاء سو كلين', null],
        ['re-email-list-2024', 'real-estate', 'RE email list 2024', 'قائمة بريد العقارات 2024', 2024],
    ];

    /** Relative size of each demo source. */
    private const array SOURCE_WEIGHTS = [
        'call-centre-lists-2013' => 30, 'waseet-business-directory' => 18, 'layalina-subscribers' => 12,
        'top-gear-gala-subscribers' => 8, 'real-estate-agents' => 7, 're-email-list-2024' => 8,
        'designer-outlet-customers' => 7, 'soclean-customers' => 5, 'photographers-uae-community' => 3,
        'photographers-award-entrants' => 2,
    ];

    public function run(RefreshFacetCountsAction $refreshFacetCounts, int $count = 1_000_000): void
    {
        if (DB::table('contacts')->exists()) {
            if (! $this->command?->confirm('Contacts already exist. Delete ALL contacts and reload demo data?', false)) {
                $this->command?->warn('Skipped: contacts table is not empty.');

                return;
            }

            DB::statement('TRUNCATE contacts, contact_phones, contact_emails, contact_sources, contact_events RESTART IDENTITY CASCADE');
        }

        if (! Region::query()->exists()) {
            Artisan::call('app:import-all');
        }
        $this->createDemoSources();

        $generator = new DemoContactGenerator($this->lookups());
        $pdo = DB::connection()->getPdo();

        if (! $pdo instanceof Pgsql) {
            throw new RuntimeException('The demo seeder needs a PostgreSQL connection.');
        }

        $this->command?->getOutput()->progressStart($count);

        for ($firstId = 1; $firstId <= $count; $firstId += self::CHUNK) {
            $lastId = min($firstId + self::CHUNK - 1, $count);
            $batch = $generator->batch($firstId, $lastId);

            $this->copy($pdo, 'contacts', DemoContactGenerator::CONTACT_COLUMNS, $batch['contacts']);
            $this->copy($pdo, 'contact_phones', DemoContactGenerator::PHONE_COLUMNS, $batch['phones']);
            $this->copy($pdo, 'contact_emails', DemoContactGenerator::EMAIL_COLUMNS, $batch['emails']);
            $this->copy($pdo, 'contact_sources', DemoContactGenerator::SOURCE_COLUMNS, $batch['sources']);

            $this->command?->getOutput()->progressAdvance($lastId - $firstId + 1);
        }

        $this->command?->getOutput()->progressFinish();

        DB::statement("SELECT setval(pg_get_serial_sequence('contacts', 'id'), (SELECT coalesce(max(id), 1) FROM contacts))");
        DB::statement('ANALYZE contacts, contact_phones, contact_emails, contact_sources');
        $refreshFacetCounts->handle();
    }

    /**
     * Demo-only sources (real sources are created from imported files).
     */
    private function createDemoSources(): void
    {
        $groups = SourceGroup::query()->pluck('id', 'slug');

        foreach (self::DEMO_SOURCES as [$slug, $group, $en, $ar, $year]) {
            Source::query()->firstOrCreate(['slug' => $slug], [
                'source_group_id' => $groups[$group] ?? null,
                'name' => ['en' => $en, 'ar' => $ar],
                'collected_year' => $year,
                'lawful_basis' => LawfulBasis::Unknown,
                'notes' => 'Demo data.',
            ]);
        }
    }

    /**
     * @param  list<string>  $columns
     * @param  list<string>  $lines
     */
    private function copy(Pgsql $pdo, string $table, array $columns, array $lines): void
    {
        if ($lines === []) {
            return;
        }

        // null_as is placed inside an E'' string by PDO, so the backslash must be escaped once more.
        if (! $pdo->copyFromArray($table, $lines, "\t", '\\\\N', implode(',', $columns))) {
            throw new RuntimeException("COPY into {$table} failed.");
        }
    }

    /**
     * @return array{
     *     countries: array<string, int>,
     *     regions: array<string, int>,
     *     areas: array<string, list<int>>,
     *     sectors: list<int>,
     *     sources: list<array{id: int, slug: string, year: int|null, weight: int}>,
     * }
     */
    private function lookups(): array
    {
        $regions = [];
        foreach (Region::query()->get(['id', 'slug']) as $region) {
            $regions[$region->slug] = $region->id;
        }
        $regionSlugs = array_flip($regions);

        $areas = [];
        foreach (Area::query()->orderBy('id')->get(['id', 'region_id']) as $area) {
            $areas[$regionSlugs[$area->region_id]][] = $area->id;
        }

        $countries = [];
        foreach (Country::query()->get(['id', 'iso2']) as $country) {
            $countries[$country->iso2] = $country->id;
        }

        $sectors = [];
        foreach (Sector::query()->orderBy('id')->get(['id']) as $sector) {
            $sectors[] = $sector->id;
        }

        $sources = [];
        foreach (Source::query()->orderBy('id')->get(['id', 'slug', 'collected_year']) as $source) {
            $sources[] = [
                'id' => $source->id,
                'slug' => $source->slug,
                'year' => $source->collected_year,
                'weight' => self::SOURCE_WEIGHTS[$source->slug] ?? 3,
            ];
        }

        return [
            'countries' => $countries,
            'regions' => $regions,
            'areas' => $areas,
            'sectors' => $sectors,
            'sources' => $sources,
        ];
    }
}
