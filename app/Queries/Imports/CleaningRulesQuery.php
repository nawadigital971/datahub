<?php

namespace App\Queries\Imports;

use App\Enums\CleaningRuleType;
use App\Models\CleaningRule;
use App\Queries\Audience\AudienceCache;
use Illuminate\Container\Attributes\Scoped;

/**
 * The cleaning rules used by the import normalizers, in order. Cached (busted with the Audience cache
 * whenever reference data or rules change) and memoized per request / queued job.
 */
#[Scoped]
class CleaningRulesQuery
{
    /** @var array<string, list<array{key: string, value: string|null}>>|null */
    private ?array $rules = null;

    public function __construct(private AudienceCache $cache) {}

    /**
     * @return list<array{key: string, value: string|null}>
     */
    public function list(CleaningRuleType $type): array
    {
        return $this->all()[$type->value] ?? [];
    }

    /**
     * key => value, first rule wins for duplicate keys.
     *
     * @return array<string, string|null>
     */
    public function map(CleaningRuleType $type): array
    {
        $map = [];
        foreach ($this->list($type) as $rule) {
            $map[$rule['key']] ??= $rule['value'];
        }

        return $map;
    }

    /**
     * @return array<string, list<array{key: string, value: string|null}>>
     */
    private function all(): array
    {
        return $this->rules ??= $this->cache->remember('cleaning-rules', 'all', function (): array {
            $rules = [];
            foreach (CleaningRule::query()->orderBy('position')->orderBy('id')->get() as $rule) {
                $rules[$rule->type->value][] = ['key' => $rule->key, 'value' => $rule->value];
            }

            return $rules;
        }, 3600);
    }
}
