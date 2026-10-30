<?php

namespace App\Actions\Normalization;

use Normalizer;

/**
 * The PHP twin of the SQL function nawaa_normalize_text(): أ/إ/آ/ٱ→ا, ة→ه, ى→ي, strip tashkeel and
 * tatweel, strip Latin accents, lowercase, collapse spaces. Used for matching names and areas.
 */
class NormalizeArabicTextAction
{
    public function handle(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ة' => 'ه', 'ى' => 'ي', 'ـ' => '']);
        $decomposed = Normalizer::normalize($text, Normalizer::FORM_D) ?: $text;
        $stripped = (string) preg_replace('/[\p{Mn}\x{0640}]/u', '', $decomposed);
        $composed = Normalizer::normalize($stripped, Normalizer::FORM_C) ?: $stripped;

        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($composed)));
    }
}
