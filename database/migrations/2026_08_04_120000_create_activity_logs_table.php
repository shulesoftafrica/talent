<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per real page view (not asset requests, not JSON/AJAX calls —
 * see App\Http\Middleware\TrackUserActivity for the exact filter). Powers
 * marketing/product analytics: most-visited pages, time on page, device
 * mix, and access patterns over time. duration_ms starts null and is
 * filled in later by a beacon fired when the candidate leaves the page
 * (see ActivityPingController) — most rows will briefly have a null
 * duration until that beacon lands.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->nullable()->constrained('candidates')->nullOnDelete();
            $table->string('session_id', 100)->nullable();
            $table->uuid('page_token')->unique();

            $table->string('method', 10);
            $table->string('path', 512);
            $table->string('route_name')->nullable();
            $table->string('referrer_path', 512)->nullable();

            $table->string('ip_address', 45)->nullable();
            // Resolved from the candidate's own profile (Candidate::country_id)
            // when known — this is not live IP geolocation. See middleware
            // docblock for why, and how to extend it if real-time IP-based
            // location for anonymous visitors is wanted later.
            $table->unsignedInteger('country_id')->nullable();

            $table->string('device_type', 20)->nullable(); // mobile | tablet | desktop | bot | unknown
            $table->string('browser', 40)->nullable();
            $table->string('platform', 40)->nullable();
            $table->text('user_agent')->nullable();

            $table->unsignedInteger('duration_ms')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['candidate_id', 'created_at']);
            $table->index(['route_name', 'created_at']);
            $table->index('created_at');
            $table->index('device_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
