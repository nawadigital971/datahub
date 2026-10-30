<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rows of an import batch waiting for review. `raw` is never modified; the parsed columns are editable.
     */
    public function up(): void
    {
        Schema::create('staged_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->integer('row_number');
            $table->jsonb('raw');
            $table->smallInteger('type')->nullable();
            $table->string('name_en', 200)->nullable();
            $table->string('name_ar', 200)->nullable();
            $table->string('company_name', 200)->nullable();
            $table->char('gender', 1)->nullable();
            $table->smallInteger('nationality_country_id')->nullable();
            $table->string('mobile_e164', 16)->nullable();
            $table->string('landline_e164', 16)->nullable();
            $table->string('email', 254)->nullable();
            $table->smallInteger('country_id')->nullable();
            $table->smallInteger('region_id')->nullable();
            $table->integer('area_id')->nullable();
            $table->smallInteger('sector_id')->nullable();
            $table->text('address_raw')->nullable();
            $table->smallInteger('seen_year')->nullable();
            $table->smallInteger('call_outcome')->nullable();
            $table->jsonb('extra')->nullable();
            $table->jsonb('issues')->default('[]');
            $table->smallInteger('severity')->default(0);
            $table->smallInteger('match_status')->default(1);
            $table->foreignId('matched_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('dup_of_row_id')->nullable()->constrained('staged_rows')->nullOnDelete();
            $table->smallInteger('action')->default(1);
            $table->jsonb('merge_choices')->nullable();
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('edited_at')->nullable();

            $table->foreign('nationality_country_id')->references('id')->on('countries')->nullOnDelete();
            $table->foreign('country_id')->references('id')->on('countries')->nullOnDelete();
            $table->foreign('region_id')->references('id')->on('regions')->nullOnDelete();
            $table->foreign('area_id')->references('id')->on('areas')->nullOnDelete();
            $table->foreign('sector_id')->references('id')->on('sectors')->nullOnDelete();

            $table->unique(['import_batch_id', 'row_number']);
            $table->index(['import_batch_id', 'severity']);
            $table->index(['import_batch_id', 'match_status']);
            $table->index(['import_batch_id', 'action']);
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE staged_rows ADD COLUMN issue_codes text[] NOT NULL DEFAULT '{}';
            CREATE INDEX staged_rows_issue_codes_gin ON staged_rows USING gin (issue_codes);
            SQL);

        Schema::create('staged_row_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staged_row_id')->constrained()->cascadeOnDelete();
            $table->string('field', 50);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('staged_row_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staged_row_edits');
        Schema::dropIfExists('staged_rows');
    }
};
