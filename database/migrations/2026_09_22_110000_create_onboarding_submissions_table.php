<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-HRX-07: what a new hire submits for each onboarding requirement. Talent
 * owns this table; the HR module reads it and marks the matching item
 * `submitted`. `synced_at` and `processing_error` are written by the HR module
 * (the shared-ownership exception in the integration contract).
 *
 * `data` is encrypted JSON (identity number, TIN, pension details ...) using
 * the key shared by both apps; `files` is a JSON list of
 * {path, original_name, mime, size, sha256, label}.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('onboarding_submissions')) {
            return;
        }

        Schema::create('onboarding_submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('source_schema', 30);
            $table->unsignedBigInteger('source_application_id');
            $table->string('requirement_code', 60);
            $table->text('data')->nullable();
            $table->json('files')->nullable();
            $table->string('status', 10)->default('draft'); // draft | submitted
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('synced_at')->nullable();
            $table->text('processing_error')->nullable();
            $table->timestamps();

            $table->unique(['source_schema', 'source_application_id', 'requirement_code'], 'onboarding_submissions_one_per_requirement');
            $table->index(['status', 'synced_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_submissions');
    }
};
