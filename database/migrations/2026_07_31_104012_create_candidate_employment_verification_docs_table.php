<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_employment_verification_docs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_verification_item_id')->constrained('candidate_verification_items')->cascadeOnDelete();
            $table->foreignId('candidate_experience_id')->constrained('candidate_experiences')->cascadeOnDelete();

            $table->string('employer_name')->nullable();
            $table->string('employer_address')->nullable();
            $table->string('employer_website')->nullable();
            $table->string('hr_email')->nullable();
            $table->string('supervisor_name')->nullable();
            $table->string('supervisor_email')->nullable();
            $table->string('supervisor_phone')->nullable();

            $table->string('status')->default('waiting_documents');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['candidate_verification_item_id', 'candidate_experience_id'], 'cevd2_item_experience_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_employment_verification_docs');
    }
};
