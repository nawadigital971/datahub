<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Postgres does not index foreign keys by itself. Without these, deleting staged rows at commit
     * (dup_of_row_id ON DELETE SET NULL) or deleting a contact scans the whole staged_rows table per row.
     */
    public function up(): void
    {
        DB::statement('CREATE INDEX staged_rows_dup_of_row_id_index ON staged_rows (dup_of_row_id) WHERE dup_of_row_id IS NOT NULL');
        DB::statement('CREATE INDEX staged_rows_matched_contact_id_index ON staged_rows (matched_contact_id) WHERE matched_contact_id IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS staged_rows_matched_contact_id_index');
        DB::statement('DROP INDEX IF EXISTS staged_rows_dup_of_row_id_index');
    }
};
