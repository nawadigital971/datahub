<?php

namespace App\Actions\Normalization;

use App\DTOs\Normalization\EmailResult;
use App\Enums\CleaningRuleType;
use App\Queries\Imports\CleaningRulesQuery;

/**
 * Cleans email cells: lowercase, trim, strip "mailto:" and stray punctuation, split several addresses,
 * tell web addresses apart, and suggest a fix for common domain typos (the fix is only applied when accepted).
 */
class NormalizeEmailAction
{
    public function __construct(private CleaningRulesQuery $rules) {}

    /**
     * @return list<EmailResult>
     */
    public function handle(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $parts = preg_split('/[\s,;\/|]+/u', trim($raw)) ?: [];

        $results = [];
        foreach ($parts as $part) {
            $part = trim($part, " \t\n\r\0\x0B<>\"'().:;,");
            if ($part !== '') {
                $results[] = $this->normalize($part);
            }
        }

        return $results;
    }

    private function normalize(string $raw): EmailResult
    {
        $value = mb_strtolower((string) preg_replace('/^mailto:/i', '', $raw));

        if (! str_contains($value, '@')) {
            $looksLikeWebsite = preg_match('/^(https?:\/\/)?(www\.)?[a-z0-9-]+(\.[a-z0-9-]+)+(\/\S*)?$/', $value) === 1;

            return new EmailResult($raw, null, false, website: $looksLikeWebsite ? $value : null);
        }

        $valid = filter_var($value, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE) !== false;

        return new EmailResult($raw, $value, $valid, $valid ? $this->suggestion($value) : null);
    }

    private function suggestion(string $email): ?string
    {
        [$local, $domain] = explode('@', $email, 2);

        $domainFixes = $this->rules->map(CleaningRuleType::EmailDomainFix);
        if (($domainFixes[$domain] ?? null) !== null) {
            return $local.'@'.$domainFixes[$domain];
        }

        foreach ($this->rules->map(CleaningRuleType::EmailTldFix) as $typo => $fix) {
            if ($fix !== null && str_ends_with($domain, $typo)) {
                return $local.'@'.substr($domain, 0, -strlen($typo)).$fix;
            }
        }

        return null;
    }
}
