<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// This app has no generic "users" table — candidates live in talent.candidates
// (see 2026_07_29_000001_create_candidates_table) and verification officers
// authenticate against the existing admin.users on the 'admin' connection.
// This migration is kept only for the session store table Laravel's
// SESSION_DRIVER=database needs.
//
// Column stays named "user_id" (holding the candidate id) because
// Illuminate\Session\DatabaseSessionHandler hardcodes that column name.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
