<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-HRX-04: the inbox of offer responses made on Talent. Talent writes only
 * this table; the HR module reads it and applies each row itself (REQ-HRX-05).
 * `processed_at`, `processing_result` and `processing_error` are written by the
 * HR module -- the one shared-ownership exception in the integration contract.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('offer_responses')) {
            return;
        }

        Schema::create('offer_responses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('source_schema', 30);
            $table->unsignedBigInteger('source_application_id');
            $table->unsignedInteger('offer_version');
            $table->string('action', 10); // accept | decline
            $table->string('decline_reason', 40)->nullable();
            $table->string('decline_detail', 400)->nullable();
            $table->string('signed_name', 150)->nullable();
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 300)->nullable();
            $table->timestamp('responded_at');
            $table->timestamp('processed_at')->nullable();
            $table->string('processing_result', 40)->nullable(); // applied | already_responded | expired | invalid | error
            $table->text('processing_error')->nullable();
            $table->timestamps();

            $table->unique(['source_schema', 'source_application_id', 'offer_version'], 'offer_responses_one_per_offer_version');
            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_responses');
    }
};
