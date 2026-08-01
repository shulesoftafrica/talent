<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_verification_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_verification_item_id')->constrained('candidate_verification_items')->cascadeOnDelete();
            // Company must be chosen from an existing Employment History entry.
            $table->foreignId('candidate_experience_id')->constrained('candidate_experiences')->cascadeOnDelete();

            $table->string('full_name');
            $table->string('position');
            $table->string('relationship'); // direct_supervisor | line_manager | department_manager | business_owner | hr_representative | project_lead | other
            $table->string('corporate_email')->nullable();
            $table->string('phone')->nullable();
            $table->date('worked_together_from')->nullable();
            $table->date('worked_together_to')->nullable();

            // Advisory fraud-prevention flag (Part 11) — free email provider
            // used instead of a corporate domain. Never blocks submission.
            $table->boolean('low_confidence_email')->default(false);

            $table->string('status')->default('waiting_documents');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_verification_references');
    }
};
