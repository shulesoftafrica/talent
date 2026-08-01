<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('officer_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_verification_item_id')->constrained('candidate_verification_items')->cascadeOnDelete();
            // Soft reference — admin.users lives in a separate schema/team
            // boundary, same reasoning as candidate_verification_items.reviewed_by.
            $table->unsignedBigInteger('officer_id');
            $table->string('officer_name')->nullable();
            $table->string('action'); // approve, reject, request_more_info, escalate, note
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('candidate_verification_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('officer_action_logs');
    }
};
