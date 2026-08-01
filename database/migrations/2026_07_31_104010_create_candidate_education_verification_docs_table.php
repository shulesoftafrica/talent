<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_education_verification_docs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_verification_item_id')->constrained('candidate_verification_items')->cascadeOnDelete();
            $table->foreignId('candidate_education_id')->constrained('candidate_educations')->cascadeOnDelete();

            $table->string('certificate_path')->nullable(); // required
            $table->string('transcript_path')->nullable(); // optional

            $table->string('status')->default('waiting_documents');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['candidate_verification_item_id', 'candidate_education_id'], 'cevd_item_education_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_education_verification_docs');
    }
};
