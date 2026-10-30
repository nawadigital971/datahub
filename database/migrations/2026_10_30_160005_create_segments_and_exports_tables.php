<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saved audience filters and the export log (every export is kept with its purpose).
     */
    public function up(): void
    {
        Schema::create('segments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->jsonb('filters');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_shared')->default(false);
            $table->integer('cached_count')->nullable();
            $table->timestamp('counted_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('segment_id')->nullable()->constrained()->nullOnDelete();
            $table->jsonb('filters');
            $table->smallInteger('channel');
            $table->boolean('hashed')->default(false);
            $table->integer('row_count')->nullable();
            $table->smallInteger('status')->default(1);
            $table->text('purpose');
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exports');
        Schema::dropIfExists('segments');
    }
};
