<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Distinguishes which backend actually served a request — 'openai' or the
 * self-hosted 'ollama' fallback — so cost reporting doesn't conflate paid
 * OpenAI spend with free self-hosted usage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->string('provider')->default('openai')->after('candidate_id');
        });

        DB::table('ai_usage_logs')->update(['provider' => 'openai']);
    }

    public function down(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->dropColumn('provider');
        });
    }
};
