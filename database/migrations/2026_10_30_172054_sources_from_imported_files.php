<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sources are no longer a fixed list: they are created from the files that are imported.
     * A file may name a source per row (a "Source" column); rows without one use the batch's default
     * source (chosen at upload, or named after the file). Sources are created when the batch is committed.
     */
    public function up(): void
    {
        Schema::table('sources', function (Blueprint $table) {
            $table->smallInteger('source_group_id')->nullable()->change();
        });

        Schema::table('import_batches', function (Blueprint $table) {
            $table->smallInteger('source_id')->nullable()->change();
            $table->string('source_name', 120)->nullable()->after('source_id');
        });

        Schema::table('staged_rows', function (Blueprint $table) {
            $table->string('source_name', 120)->nullable()->after('row_number');
            $table->smallInteger('source_id')->nullable()->after('source_name');

            $table->foreign('source_id')->references('id')->on('sources')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staged_rows', function (Blueprint $table) {
            $table->dropForeign(['source_id']);
            $table->dropColumn(['source_name', 'source_id']);
        });

        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropColumn('source_name');
        });
    }
};
