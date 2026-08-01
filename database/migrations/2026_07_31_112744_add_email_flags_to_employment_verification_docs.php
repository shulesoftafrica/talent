<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Advisory-only fraud flags (Part 11) — HR/supervisor contact using a free
 * email provider instead of a corporate domain. Never blocks submission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_employment_verification_docs', function (Blueprint $table) {
            $table->boolean('hr_email_low_confidence')->default(false)->after('hr_email');
            $table->boolean('supervisor_email_low_confidence')->default(false)->after('supervisor_email');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_employment_verification_docs', function (Blueprint $table) {
            $table->dropColumn(['hr_email_low_confidence', 'supervisor_email_low_confidence']);
        });
    }
};
