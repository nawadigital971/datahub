<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapping templates and import batches. Staged rows are created after contacts (they reference them).
     */
    public function up(): void
    {
        Schema::create('mapping_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->smallInteger('layout');
            $table->jsonb('column_map');
            $table->timestamps();
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('source_id');
            $table->smallInteger('origin');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('mapping_template_id')->nullable()->constrained()->nullOnDelete();
            $table->smallInteger('status');
            $table->string('original_filename')->nullable();
            $table->integer('total_rows')->default(0);
            $table->integer('clean_rows')->default(0);
            $table->integer('warning_rows')->default(0);
            $table->integer('error_rows')->default(0);
            $table->integer('new_rows')->default(0);
            $table->integer('merge_rows')->default(0);
            $table->integer('dup_in_batch_rows')->default(0);
            $table->integer('conflict_rows')->default(0);
            $table->integer('skipped_rows')->default(0);
            $table->foreignId('committed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('discarded_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('source_id')->references('id')->on('sources');
            $table->index(['status', 'created_at']);
            $table->index('source_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('mapping_templates');
    }
};
