<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Everything the import cleaning relies on is data, editable in the dashboard and by Claude over MCP:
     * spellings of emirates and countries, and a table of cleaning rules (call codes, email typo fixes,
     * junk names, first names, landline prefixes, header hints, blocked file names).
     */
    public function up(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->jsonb('aliases')->default('[]');
        });

        Schema::table('countries', function (Blueprint $table) {
            $table->jsonb('aliases')->default('[]');
        });

        Schema::create('cleaning_rules', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->string('key', 200);
            $table->string('value', 200)->nullable();
            $table->integer('position')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['type', 'key']);
            $table->index(['type', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cleaning_rules');

        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn('aliases');
        });

        Schema::table('regions', function (Blueprint $table) {
            $table->dropColumn('aliases');
        });
    }
};
