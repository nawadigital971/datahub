<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A contact can be known under several names (the same phone arrives with a different name in another
     * file). name_en / name_ar stay the main names; every other name seen is kept in other_names and is
     * searchable. search_text is a generated column, so it is rebuilt to include them (one table rewrite).
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION nawaa_merge_names(existing text[], incoming text[], primary_en text, primary_ar text)
            RETURNS text[]
            LANGUAGE sql IMMUTABLE PARALLEL SAFE
            AS $$
                SELECT coalesce(array_agg(name ORDER BY first_at), '{}')
                FROM (
                    SELECT name, first_at FROM (
                        SELECT DISTINCT ON (nawaa_normalize_text(n.name)) btrim(n.name) AS name, n.ord AS first_at
                        FROM unnest(coalesce(existing, '{}') || coalesce(incoming, '{}')) WITH ORDINALITY AS n(name, ord)
                        WHERE nullif(btrim(n.name), '') IS NOT NULL
                          AND nawaa_normalize_text(n.name) IS DISTINCT FROM nawaa_normalize_text(primary_en)
                          AND nawaa_normalize_text(n.name) IS DISTINCT FROM nawaa_normalize_text(primary_ar)
                        ORDER BY nawaa_normalize_text(n.name), n.ord
                    ) distinct_names
                    ORDER BY first_at
                    LIMIT 20
                ) kept
            $$;

            CREATE OR REPLACE FUNCTION nawaa_search_text(
                name_en text, name_ar text, company_name text, email text, mobile_e164 text, landline_e164 text, other_names text[]
            ) RETURNS text
            LANGUAGE sql IMMUTABLE PARALLEL SAFE
            AS $$
                SELECT btrim(concat_ws(' ',
                    nawaa_normalize_text(nullif(concat_ws(' ', name_en, name_ar, company_name, email, array_to_string(other_names, ' ')), '')),
                    regexp_replace(mobile_e164, '\D', '', 'g'),
                    regexp_replace(landline_e164, '\D', '', 'g')
                ))
            $$;

            ALTER TABLE contacts ADD COLUMN other_names text[] NOT NULL DEFAULT '{}';

            ALTER TABLE contacts DROP COLUMN search_text;
            ALTER TABLE contacts ADD COLUMN search_text text NOT NULL GENERATED ALWAYS AS
                (nawaa_search_text(name_en, name_ar, company_name, email, mobile_e164, landline_e164, other_names)) STORED;
            CREATE INDEX contacts_search_text_trgm ON contacts USING gin (search_text gin_trgm_ops);
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE contacts DROP COLUMN search_text;
            ALTER TABLE contacts DROP COLUMN other_names;
            ALTER TABLE contacts ADD COLUMN search_text text NOT NULL GENERATED ALWAYS AS
                (nawaa_search_text(name_en, name_ar, company_name, email, mobile_e164, landline_e164)) STORED;
            CREATE INDEX contacts_search_text_trgm ON contacts USING gin (search_text gin_trgm_ops);

            DROP FUNCTION IF EXISTS nawaa_search_text(text, text, text, text, text, text, text[]);
            DROP FUNCTION IF EXISTS nawaa_merge_names(text[], text[], text, text);
            SQL);
    }
};
