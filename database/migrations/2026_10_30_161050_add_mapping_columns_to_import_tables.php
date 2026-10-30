<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The mapping actually used for a batch (a template or a custom one), the file's header row,
     * and whether a reviewer chose a row's action by hand (so re-checks never overwrite it).
     */
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->jsonb('headers')->nullable()->after('original_filename');
            $table->jsonb('column_map')->nullable()->after('headers');
        });

        Schema::table('staged_rows', function (Blueprint $table) {
            $table->boolean('action_locked')->default(false)->after('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staged_rows', function (Blueprint $table) {
            $table->dropColumn('action_locked');
        });

        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropColumn(['headers', 'column_map']);
        });
    }
};
