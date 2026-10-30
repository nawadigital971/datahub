<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reference data (filled by `php artisan app:import-all`). Names are translatable json ({"en":…,"ar":…}).
     */
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->char('iso2', 2)->unique();
            $table->string('dial_code', 5);
            $table->json('name');
        });

        Schema::create('regions', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->smallInteger('country_id');
            $table->string('slug', 60)->unique();
            $table->json('name');

            $table->foreign('country_id')->references('id')->on('countries');
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->increments('id');
            $table->smallInteger('region_id');
            $table->string('slug', 80);
            $table->json('name');
            $table->jsonb('aliases')->default('[]');

            $table->unique(['region_id', 'slug']);
            $table->foreign('region_id')->references('id')->on('regions');
        });

        Schema::create('sectors', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('slug', 60)->unique();
            $table->json('name');
        });

        Schema::create('source_groups', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('slug', 60)->unique();
            $table->json('name');
        });

        Schema::create('sources', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->smallInteger('source_group_id');
            $table->string('slug', 80)->unique();
            $table->json('name');
            $table->smallInteger('collected_year')->nullable();
            $table->smallInteger('lawful_basis')->default(4);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('source_group_id')->references('id')->on('source_groups');
            $table->index('source_group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sources');
        Schema::dropIfExists('source_groups');
        Schema::dropIfExists('sectors');
        Schema::dropIfExists('areas');
        Schema::dropIfExists('regions');
        Schema::dropIfExists('countries');
    }
};
