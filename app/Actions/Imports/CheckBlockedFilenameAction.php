<?php

namespace App\Actions\Imports;

use App\Enums\CleaningRuleType;
use App\Queries\Imports\CleaningRulesQuery;

class CheckBlockedFilenameAction
{
    public function __construct(private CleaningRulesQuery $rules) {}

    /**
     * The reason a file (or MCP batch label) must not be imported, or null when it is allowed.
     */
    public function handle(string $name): ?string
    {
        $haystack = ' '.trim((string) preg_replace('/[\s_\-.]+/u', ' ', mb_strtolower($name))).' ';

        foreach ($this->rules->list(CleaningRuleType::BlockedFilename) as $rule) {
            $needle = trim((string) preg_replace('/[\s_\-.]+/u', ' ', mb_strtolower($rule['key'])));
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return $rule['value'] ?? 'This file is on the blocked list.';
            }
        }

        return null;
    }
}
