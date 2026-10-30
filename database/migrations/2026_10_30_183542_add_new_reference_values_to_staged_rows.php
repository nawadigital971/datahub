<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Values a row names that are not in the reference lists yet. They are created when the batch is
     * committed (countries from ISO codes; regions, areas and sectors from the text in the file).
     */
    public function up(): void
    {
        Schema::table('staged_rows', function (Blueprint $table) {
            $table->char('country_iso', 2)->nullable();
            $table->char('nationality_iso', 2)->nullable();
            $table->string('region_name', 120)->nullable();
            $table->string('area_name', 120)->nullable();
            $table->string('sector_name', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('staged_rows', function (Blueprint $table) {
            $table->dropColumn(['country_iso', 'nationality_iso', 'region_name', 'area_name', 'sector_name']);
        });
    }
};
