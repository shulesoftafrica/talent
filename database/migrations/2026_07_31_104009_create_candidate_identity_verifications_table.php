<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_identity_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('candidate_verification_item_id')->constrained('candidate_verification_items')->cascadeOnDelete();
            $table->unique('candidate_verification_item_id', 'civ_item_unique');

            // Primary government identity — exactly one of these three, PDF only.
            $table->string('primary_doc_type')->nullable(); // national_id | passport | driving_licence
            $table->string('primary_doc_path')->nullable();
            $table->unsignedInteger('primary_doc_size_bytes')->nullable();

            // Optional supporting documents.
            $table->string('tin_certificate_path')->nullable();
            $table->string('local_government_letter_path')->nullable();
            $table->string('pension_fund_number')->nullable();

            $table->string('status')->default('waiting_documents');
            $table->timestamp('declaration_accepted_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_identity_verifications');
    }
};
