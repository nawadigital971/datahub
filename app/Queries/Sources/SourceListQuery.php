<?php

namespace App\Queries\Sources;

use App\Models\Source;
use App\Queries\Audience\AudienceCache;
use Illuminate\Support\Facades\DB;

/**
 * Every source with its group, year, lawful basis, how many contacts came from it and when it was last imported.
 *
 * @phpstan-type SourceRow array{id: int, slug: string, name: array<string, string>, label: string, group_id: int, group: string|null, collected_year: int|null, lawful_basis: int, lawful_basis_label: string, notes: string|null, contacts: int, last_import: string|null}
 */
class SourceListQuery
{
    public function __construct(private AudienceCache $cache) {}

    /**
     * @return list<SourceRow>
     */
    public function handle(): array
    {
        /** @var array<int, array{total: int, last: string|null}> $stats */
        $stats = $this->cache->remember('source-contact-counts', 'all', function (): array {
            $rows = [];
            foreach (DB::table('contact_sources')->selectRaw('source_id, count(*) AS total, max(created_at) AS last')->groupBy('source_id')->get() as $row) {
                $rows[(int) $row->source_id] = ['total' => (int) $row->total, 'last' => $row->last === null ? null : (string) $row->last];
            }

            return $rows;
        });

        return array_values(Source::query()->with('group')->orderBy('id')->get()->map(fn (Source $source): array => [
            'id' => $source->id,
            'slug' => $source->slug,
            'name' => $source->getTranslations('name'),
            'label' => $source->name,
            'group_id' => $source->source_group_id,
            'group' => $source->group?->name,
            'collected_year' => $source->collected_year,
            'lawful_basis' => $source->lawful_basis->value,
            'lawful_basis_label' => $source->lawful_basis->label(),
            'notes' => $source->notes,
            'contacts' => $stats[$source->id]['total'] ?? 0,
            'last_import' => $stats[$source->id]['last'] ?? null,
        ])->all());
    }
}
