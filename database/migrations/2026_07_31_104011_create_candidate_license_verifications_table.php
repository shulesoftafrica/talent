<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_license_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('candidate_verification_item_id')->constrained('candidate_verification_items')->cascadeOnDelete();
            $table->unique('candidate_verification_item_id', 'clv_item_unique');

            $table->string('license_name')->nullable();
            $table->string('license_number')->nullable();
            $table->string('issuing_authority')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('verification_url')->nullable();
            $table->string('certificate_path')->nullable();

            $table->string('status')->default('waiting_documents');
            $table->timestamp('declaration_accepted_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_license_verifications');
    }
};
