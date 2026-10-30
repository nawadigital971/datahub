<?php

namespace App\Queries\Imports;

use App\Enums\IssueCode;
use App\Enums\MatchStatus;
use App\Enums\RowSeverity;
use App\Enums\StagedRowAction;
use App\Models\Area;
use App\Models\Country;
use App\Models\Region;
use App\Models\Sector;
use App\Queries\Audience\AudienceCache;

/**
 * Choices for the preview's selects and filters (labels in the current locale).
 */
class ImportReviewOptionsQuery
{
    public function __construct(private AudienceCache $cache) {}

    /**
     * @return array<string, array<int, array<string, int|string>>>
     */
    public function handle(): array
    {
        return $this->cache->remember('import-review-options', app()->getLocale(), fn (): array => [
            'regions' => Region::query()->orderBy('id')->get()->map(fn (Region $r): array => ['value' => $r->id, 'label' => $r->name])->all(),
            'areas' => Area::query()->orderBy('slug')->get()->map(fn (Area $a): array => ['value' => $a->id, 'label' => $a->name, 'region_id' => $a->region_id])->all(),
            'sectors' => Sector::query()->orderBy('id')->get()->map(fn (Sector $s): array => ['value' => $s->id, 'label' => $s->name])->all(),
            'countries' => Country::query()->orderBy('iso2')->get()->map(fn (Country $c): array => ['value' => $c->id, 'label' => $c->name])->all(),
            'issues' => array_map(fn (IssueCode $code): array => ['value' => $code->value, 'label' => $code->label(), 'severity' => $code->severity()->key()], IssueCode::cases()),
            'matches' => array_map(fn (MatchStatus $status): array => ['value' => $status->key(), 'label' => $status->label()], MatchStatus::cases()),
            'actions' => array_map(fn (StagedRowAction $action): array => ['value' => $action->key(), 'label' => $action->label()], StagedRowAction::cases()),
            'severities' => array_map(fn (RowSeverity $severity): array => ['value' => $severity->key(), 'label' => $severity->label()], RowSeverity::cases()),
        ], 3600);
    }
}
