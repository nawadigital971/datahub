<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Unfiltered facet counts over contactable people (do_not_contact = false), read by the Audience page
     * when no filter is applied. value_id 0 means "unknown"; boolean dimensions use 0/1.
     * Refreshed concurrently after every import commit (needs the unique index).
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE MATERIALIZED VIEW facet_counts AS
                SELECT d.dimension, d.value_id, count(*)::bigint AS total
                FROM contacts c
                CROSS JOIN LATERAL (VALUES
                    ('type', c.type::int),
                    ('country', coalesce(c.country_id, 0)::int),
                    ('nationality', coalesce(c.nationality_country_id, 0)::int),
                    ('region', coalesce(c.region_id, 0)::int),
                    ('area', coalesce(c.area_id, 0)),
                    ('sector', coalesce(c.sector_id, 0)::int),
                    ('gender', CASE c.gender WHEN 'm' THEN 1 WHEN 'f' THEN 2 ELSE 0 END),
                    ('last_seen_year', coalesce(c.last_seen_year, 0)::int),
                    ('call_outcome', coalesce(c.last_call_outcome, 0)::int),
                    ('has_mobile', c.has_mobile::int),
                    ('has_uae_mobile', c.has_uae_mobile::int),
                    ('has_email', c.has_email::int),
                    ('has_landline', c.has_landline::int),
                    ('has_arabic_name', c.has_arabic_name::int),
                    ('is_junk', c.is_junk::int)
                ) AS d(dimension, value_id)
                WHERE c.do_not_contact = false
                GROUP BY d.dimension, d.value_id
            UNION ALL
                SELECT 'source', s.source_id::int, count(*)::bigint
                FROM contacts c
                CROSS JOIN LATERAL unnest(c.source_ids) AS s(source_id)
                WHERE c.do_not_contact = false
                GROUP BY s.source_id
            UNION ALL
                SELECT 'total', 0, count(*)::bigint
                FROM contacts c
                WHERE c.do_not_contact = false;

            CREATE UNIQUE INDEX facet_counts_dimension_value_unique ON facet_counts (dimension, value_id);
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS facet_counts');
    }
};
