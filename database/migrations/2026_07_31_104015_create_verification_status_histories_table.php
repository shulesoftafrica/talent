<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_status_histories', function (Blueprint $table) {
            $table->id();

            // Polymorphic — points at candidate_verification_items, or any
            // category-detail table (candidate_identity_verifications,
            // candidate_education_verification_docs, etc.). One shared audit
            // table instead of duplicating a history table per category.
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');

            $table->string('from_status')->nullable();
            $table->string('to_status');

            // Who made the change — 'candidate' | 'officer' | 'system' (e.g. webhook).
            $table->string('actor_type');
            $table->unsignedBigInteger('actor_id')->nullable();

            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_status_histories');
    }
};
