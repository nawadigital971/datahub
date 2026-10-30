<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per real person or business (millions of rows). Every filter dimension is a small-int FK
     * or boolean directly on this table so Postgres can combine indexes with bitmap scans.
     */
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('type');
            $table->string('name_en', 200)->nullable();
            $table->string('name_ar', 200)->nullable();
            $table->string('company_name', 200)->nullable();
            $table->boolean('name_en_verified')->default(true);
            $table->char('gender', 1)->nullable();
            $table->boolean('gender_trusted')->default(false);
            $table->smallInteger('nationality_country_id')->nullable();
            $table->smallInteger('country_id')->nullable();
            $table->smallInteger('region_id')->nullable();
            $table->integer('area_id')->nullable();
            $table->smallInteger('sector_id')->nullable();
            $table->text('address_raw')->nullable();
            $table->string('mobile_e164', 16)->nullable();
            $table->string('landline_e164', 16)->nullable();
            $table->string('email', 254)->nullable();
            $table->smallInteger('email_status')->default(0);
            $table->boolean('has_mobile')->storedAs('mobile_e164 IS NOT NULL');
            $table->boolean('has_uae_mobile')->storedAs("coalesce(mobile_e164 LIKE '+9715%', false)");
            $table->boolean('has_email')->storedAs('email IS NOT NULL AND email_status <> 2');
            $table->boolean('has_landline')->storedAs('landline_e164 IS NOT NULL');
            $table->boolean('has_arabic_name')->storedAs('name_ar IS NOT NULL');
            $table->smallInteger('source_count')->default(0);
            $table->smallInteger('first_seen_year')->nullable();
            $table->smallInteger('last_seen_year')->nullable();
            $table->smallInteger('last_call_outcome')->nullable();
            $table->timestampTz('last_called_at')->nullable();
            $table->boolean('do_not_contact')->default(false);
            $table->smallInteger('dnc_reason')->nullable();
            $table->boolean('is_junk')->default(false);
            $table->text('search_text')->storedAs('nawaa_search_text(name_en, name_ar, company_name, email, mobile_e164, landline_e164)');
            $table->jsonb('extra')->nullable();
            $table->timestamps();

            $table->foreign('nationality_country_id')->references('id')->on('countries');
            $table->foreign('country_id')->references('id')->on('countries');
            $table->foreign('region_id')->references('id')->on('regions');
            $table->foreign('area_id')->references('id')->on('areas');
            $table->foreign('sector_id')->references('id')->on('sectors');

            $table->index('type');
            $table->index('country_id');
            $table->index('nationality_country_id');
            $table->index(['region_id', 'area_id']);
            $table->index('sector_id');
            $table->index('gender');
            $table->index('last_seen_year');
            $table->index('last_call_outcome');
        });

        DB::unprepared(<<<'SQL'
            ALTER TABLE contacts ADD COLUMN source_ids smallint[] NOT NULL DEFAULT '{}';

            CREATE INDEX contacts_source_ids_gin ON contacts USING gin (source_ids);
            CREATE INDEX contacts_search_text_trgm ON contacts USING gin (search_text gin_trgm_ops);

            CREATE INDEX contacts_contactable_region_id_index ON contacts (region_id) WHERE do_not_contact = false;
            CREATE INDEX contacts_contactable_area_id_index ON contacts (area_id) WHERE do_not_contact = false;
            CREATE INDEX contacts_contactable_country_id_index ON contacts (country_id) WHERE do_not_contact = false;
            CREATE INDEX contacts_contactable_sector_id_index ON contacts (sector_id) WHERE do_not_contact = false;
            SQL);

        Schema::create('contact_phones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('e164', 16)->unique();
            $table->smallInteger('type');
            $table->smallInteger('country_id')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('country_id')->references('id')->on('countries');
            $table->index('contact_id');
        });

        Schema::create('contact_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('email', 254)->unique();
            $table->boolean('is_primary')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index('contact_id');
        });

        Schema::create('contact_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('source_id');
            $table->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('row_number')->nullable();
            $table->smallInteger('seen_year')->nullable();
            $table->jsonb('raw');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('source_id')->references('id')->on('sources');
            $table->unique(['contact_id', 'source_id']);
            $table->index('source_id');
            $table->index('import_batch_id');
        });

        Schema::create('contact_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('type');
            $table->smallInteger('outcome')->nullable();
            $table->string('agent', 100)->nullable();
            $table->timestampTz('occurred_at');
            $table->jsonb('meta')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->index(['contact_id', 'occurred_at']);
        });

        Schema::create('suppressions', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('kind');
            $table->string('value', 254)->unique();
            $table->smallInteger('reason');
            $table->smallInteger('source_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('source_id')->references('id')->on('sources')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppressions');
        Schema::dropIfExists('contact_events');
        Schema::dropIfExists('contact_sources');
        Schema::dropIfExists('contact_emails');
        Schema::dropIfExists('contact_phones');
        Schema::dropIfExists('contacts');
    }
};
