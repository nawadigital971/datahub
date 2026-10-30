<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normalization used for contact search (rule 4 of the brief):
     * أ/إ/آ/ٱ→ا, ة→ه, ى→ي, strip tashkeel and tatweel, strip Latin accents, lowercase, collapse spaces.
     * Both functions are IMMUTABLE so contacts.search_text can be a generated column.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION nawaa_normalize_text(input text) RETURNS text
            LANGUAGE sql IMMUTABLE STRICT PARALLEL SAFE
            AS $$
                SELECT btrim(regexp_replace(lower(public.unaccent('public.unaccent'::regdictionary,
                    translate(regexp_replace(input, '[ً-ٰٟـ]', '', 'g'), 'أإآٱةى', 'ااااهي')
                )), '\s+', ' ', 'g'))
            $$;

            CREATE OR REPLACE FUNCTION nawaa_search_text(
                name_en text, name_ar text, company_name text, email text, mobile_e164 text, landline_e164 text
            ) RETURNS text
            LANGUAGE sql IMMUTABLE PARALLEL SAFE
            AS $$
                SELECT btrim(concat_ws(' ',
                    nawaa_normalize_text(nullif(concat_ws(' ', name_en, name_ar, company_name, email), '')),
                    regexp_replace(mobile_e164, '\D', '', 'g'),
                    regexp_replace(landline_e164, '\D', '', 'g')
                ))
            $$;
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP FUNCTION IF EXISTS nawaa_search_text(text, text, text, text, text, text);
            DROP FUNCTION IF EXISTS nawaa_normalize_text(text);
            SQL);
    }
};
