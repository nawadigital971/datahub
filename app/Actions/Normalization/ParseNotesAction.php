<?php

namespace App\Actions\Normalization;

use App\DTOs\Normalization\NotesResult;
use App\Enums\CallOutcome;
use App\Enums\CleaningRuleType;
use App\Queries\Imports\CleaningRulesQuery;

/**
 * Reads the Notes column: call-centre codes and phrases (cleaning rules, e.g. CB, NI, SW, D), labelled values
 * (note-label rules, e.g. "Landline: …", "contract no …", "agent: …", "Verify transliteration"), other phone
 * numbers and emails.
 */
class ParseNotesAction
{
    /** @var array<string, string|null>|null label pattern by meaning (null when there are no labels) */
    private ?array $labels = null;

    public function __construct(private CleaningRulesQuery $rules) {}

    public function handle(?string $notes): NotesResult
    {
        if ($notes === null || trim($notes) === '') {
            return new NotesResult;
        }

        $text = $notes;
        $landlines = [];
        $label = $this->labels();

        if ($label['landline'] !== null && preg_match_all('/'.$label['landline'].'\s*[:\-]\s*((?:\+|00)?[\d][\d\s\-()]{5,}\d)/iu', $text, $matches)) {
            $landlines = array_map('trim', $matches[1]);
            $text = str_replace($matches[0], ' ', $text);
        }

        $contractNo = null;
        if ($label['contract'] !== null && preg_match('/'.$label['contract'].'\s*[:#\-]?\s*([A-Za-z0-9][A-Za-z0-9\-\/]{1,30})/iu', $text, $match)) {
            $contractNo = $match[1];
            $text = str_replace($match[0], ' ', $text);
        }

        $agent = null;
        if ($label['agent'] !== null && preg_match('/'.$label['agent'].'\s*[:\-]\s*([\p{L}][\p{L} .\'-]{1,40}?)(?=\s*(?:[,;|\/\n]|$|\d))/iu', $text, $match)) {
            $agent = trim($match[1]);
        }

        $emails = [];
        if (preg_match_all('/[\w.+\-]+@[\w\-]+(?:\.[\w\-]+)+/u', $text, $matches)) {
            $emails = $matches[0];
            $text = str_replace($emails, ' ', $text);
        }

        $phones = [];
        if (preg_match_all('/(?<![\w])(?:\+|00)?\d[\d\s\-()]{6,}\d(?![\w])/u', $text, $matches)) {
            $phones = array_map('trim', $matches[0]);
        }

        return new NotesResult(
            $this->callOutcome($notes),
            $landlines,
            $phones,
            array_map('strtolower', $emails),
            $contractNo,
            $agent,
            $label['verify_transliteration'] !== null && preg_match('/'.$label['verify_transliteration'].'/iu', $notes) === 1,
        );
    }

    /**
     * One regex alternation per meaning, built from the note-label rules (longest label first, spaces
     * matching any whitespace, never starting inside a word).
     *
     * @return array<string, string|null>
     */
    private function labels(): array
    {
        if ($this->labels === null) {
            $byMeaning = array_fill_keys(CleaningRuleType::NOTE_LABEL_MEANINGS, []);
            foreach ($this->rules->list(CleaningRuleType::NoteLabel) as $rule) {
                $key = trim($rule['key']);
                if ($key !== '' && isset($byMeaning[$rule['value'] ?? ''])) {
                    $byMeaning[$rule['value']][] = $key;
                }
            }

            $this->labels = array_map(function (array $labels): ?string {
                if ($labels === []) {
                    return null;
                }
                usort($labels, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

                return '(?<![\p{L}\d])(?:'.implode('|', array_map(
                    fn (string $label): string => (string) preg_replace('/\s+/', '\\s*', preg_quote($label, '/')),
                    $labels,
                )).')';
            }, $byMeaning);
        }

        return $this->labels;
    }

    /**
     * The last outcome mentioned wins (notes are usually appended over time).
     */
    private function callOutcome(string $notes): ?CallOutcome
    {
        $found = [];
        $outcome = fn (?string $key): ?CallOutcome => collect(CallOutcome::cases())->first(fn (CallOutcome $case): bool => $case->key() === $key);

        $codes = array_filter(array_map($outcome, $this->rules->map(CleaningRuleType::CallCode)));
        if ($codes !== []) {
            $pattern = implode('|', array_map(fn (string $code): string => preg_quote($code, '/'), array_keys($codes)));
            if (preg_match_all('/(?<![\p{L}\d])('.$pattern.')(?![\p{L}\d])/u', $notes, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[1] as [$code, $offset]) {
                    $found[$offset] = $codes[$code];
                }
            }
        }

        foreach ($this->rules->map(CleaningRuleType::CallPhrase) as $phrase => $key) {
            $offset = stripos($notes, $phrase);
            $result = $outcome($key);
            if ($offset !== false && $result !== null) {
                $found[$offset] = $result;
            }
        }

        if ($found === []) {
            return null;
        }

        ksort($found);

        return end($found);
    }
}
