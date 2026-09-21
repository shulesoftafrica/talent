<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-HRX-09: what Talent last saw for each onboarding item, so a change made in the
 * HR module (returned, approved) is noticed once, and how many reminders each item
 * has had. Rows whose code starts with an underscore are application-level markers
 * (`_opened`, `_completed`, `_nothing_submitted`, `_start_date`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('onboarding_item_state')) {
            return;
        }

        Schema::create('onboarding_item_state', function (Blueprint $table) {
            $table->id();
            $table->string('source_schema', 30);
            $table->unsignedBigInteger('source_application_id');
            $table->string('requirement_code', 60);
            $table->string('last_seen_status', 20)->nullable();
            $table->unsignedSmallInteger('reminders_sent')->default(0);
            $table->timestamp('last_reminded_at')->nullable();
            $table->timestamps();

            $table->unique(['source_schema', 'source_application_id', 'requirement_code'], 'onboarding_item_state_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_item_state');
    }
};
