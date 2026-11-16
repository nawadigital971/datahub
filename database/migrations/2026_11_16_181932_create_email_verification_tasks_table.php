<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per bulk task sent to the email verifier (one per day). The submitted emails and their contact
     * ids are kept so they are not picked again while the task runs, and so results can be applied by
     * primary key when it completes (contacts.email has no index).
     */
    public function up(): void
    {
        Schema::create('email_verification_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('reoon_task_id', 50)->nullable();
            $table->smallInteger('status');
            $table->jsonb('emails');
            $table->jsonb('contact_ids');
            $table->integer('count_submitted')->default(0);
            $table->integer('count_processing')->default(0);
            $table->jsonb('summary')->nullable();
            $table->text('error')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_verification_tasks');
    }
};
