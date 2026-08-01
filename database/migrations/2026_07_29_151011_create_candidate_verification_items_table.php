<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_verification_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->foreignId('verification_type_id')->constrained('verification_types');
            $table->string('status')->default('Not Verified'); // Not Verified, Pending, Verified, Rejected
            $table->decimal('price', 8, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('payer')->default('candidate');
            $table->date('verified_on')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('method')->nullable();
            $table->timestamp('submitted_at')->nullable();
            // Soft reference only — admin.users lives in a separate schema/team
            // boundary, so this intentionally has no enforced FK constraint.
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('officer_notes')->nullable();
            $table->timestamps();

            $table->unique(['candidate_id', 'verification_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_verification_items');
    }
};
