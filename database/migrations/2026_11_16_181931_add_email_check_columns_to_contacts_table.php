<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The index is built concurrently, which cannot run inside a transaction.
     */
    public $withinTransaction = false;

    /**
     * When the primary email was last checked with the email verifier, and the verifier's raw result
     * (safe, invalid, catch_all…). Nullable without a default, so adding them does not rewrite the table.
     * The partial index serves the daily "next unchecked emails" pick.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE contacts ADD COLUMN IF NOT EXISTS email_checked_at timestamptz NULL');
        DB::statement('ALTER TABLE contacts ADD COLUMN IF NOT EXISTS email_check_result varchar(20) NULL');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS contacts_email_unchecked_index ON contacts (id) WHERE email IS NOT NULL AND email_checked_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS contacts_email_unchecked_index');
        DB::statement('ALTER TABLE contacts DROP COLUMN IF EXISTS email_check_result');
        DB::statement('ALTER TABLE contacts DROP COLUMN IF EXISTS email_checked_at');
    }
};
