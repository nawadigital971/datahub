<?php

namespace App\Actions\Imports;

use App\Actions\Normalization\NormalizeArabicTextAction;
use App\Enums\CleaningRuleType;
use App\Enums\ImportTarget;
use App\Models\MappingTemplate;
use App\Queries\Imports\CleaningRulesQuery;

/**
 * Picks the known layout that matches a file's headers and proposes a target for every column
 * (from the template, else from the header hints in the cleaning rules, English or Arabic).
 *
 * @phpstan-type Suggestion array{template: MappingTemplate|null, column_map: array<string, string>}
 */
class SuggestColumnMapAction
{
    /** A template is chosen when at least this share of its columns is present. */
    public const float TEMPLATE_THRESHOLD = 0.6;

    public function __construct(private CleaningRulesQuery $rules, private NormalizeArabicTextAction $normalize) {}

    /**
     * @param  list<string>  $headers
     * @return Suggestion
     */
    public function handle(array $headers): array
    {
        $template = $this->bestTemplate($headers);
        $templateMap = [];
        foreach ($template === null ? [] : $template->column_map as $header => $target) {
            $templateMap[$this->key($header)] = $target;
        }

        $columnMap = [];
        foreach ($headers as $header) {
            $columnMap[$header] = $templateMap[$this->key($header)] ?? $this->guess($header);
        }

        return ['template' => $template, 'column_map' => $columnMap];
    }

    /**
     * @param  list<string>  $headers
     */
    private function bestTemplate(array $headers): ?MappingTemplate
    {
        $present = array_map($this->key(...), $headers);
        $best = null;
        $bestScore = 0.0;

        foreach (MappingTemplate::query()->get() as $template) {
            $columns = array_map($this->key(...), array_keys($template->column_map));
            if ($columns === []) {
                continue;
            }

            $score = count(array_intersect($columns, $present)) / count($columns);
            if ($score > $bestScore) {
                [$best, $bestScore] = [$template, $score];
            }
        }

        return $bestScore >= self::TEMPLATE_THRESHOLD ? $best : null;
    }

    private function guess(string $header): string
    {
        $header = $this->normalize->handle($header);

        foreach ($this->rules->list(CleaningRuleType::HeaderHint) as $hint) {
            if ($hint['value'] === null || ! ImportTarget::isValid($hint['value'])) {
                continue;
            }

            $exact = str_starts_with($hint['key'], '=');
            $word = $this->normalize->handle($exact ? substr($hint['key'], 1) : $hint['key']);

            if ($word !== '' && ($exact ? $header === $word : str_contains($header, $word))) {
                return $hint['value'];
            }
        }

        return ImportTarget::Extra->value;
    }

    private function key(string $header): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($header)));
    }
}
