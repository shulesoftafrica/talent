<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Advisory-only fraud flag (Part 11) — a license/certificate number reused
 * by a different candidate account. Never blocks submission, mirrors
 * candidate_verification_references.low_confidence_email.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_license_verifications', function (Blueprint $table) {
            $table->boolean('duplicate_number_flag')->default(false)->after('license_number');
        });

        Schema::table('candidate_background_verifications', function (Blueprint $table) {
            $table->boolean('duplicate_number_flag')->default(false)->after('certificate_number');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_license_verifications', function (Blueprint $table) {
            $table->dropColumn('duplicate_number_flag');
        });

        Schema::table('candidate_background_verifications', function (Blueprint $table) {
            $table->dropColumn('duplicate_number_flag');
        });
    }
};
