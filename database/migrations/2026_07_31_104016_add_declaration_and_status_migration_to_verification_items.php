<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the declaration-acceptance timestamp and migrates existing status
 * values to the new lowercase-snake 8-value enum (see
 * App\Services\Verification\VerificationStatus):
 *   'Not Verified' -> 'waiting_payment' (never purchased/paid yet)
 *   'Pending'      -> 'waiting_documents' (paid, but the old flow never
 *                      actually collected documents — see Part 2 of the
 *                      upgrade spec, which replaces "pay -> Pending" with
 *                      "pay -> Waiting Documents -> candidate uploads ->
 *                      Documents Submitted -> officer review")
 *   'Verified'     -> 'verified'
 *   'Rejected'     -> 'rejected'
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_verification_items', function (Blueprint $table) {
            $table->timestamp('declaration_accepted_at')->nullable()->after('officer_notes');
        });

        DB::table('candidate_verification_items')->where('status', 'Not Verified')->update(['status' => 'waiting_payment']);
        DB::table('candidate_verification_items')->where('status', 'Pending')->update(['status' => 'waiting_documents']);
        DB::table('candidate_verification_items')->where('status', 'Verified')->update(['status' => 'verified']);
        DB::table('candidate_verification_items')->where('status', 'Rejected')->update(['status' => 'rejected']);

        // Note: not changing the column's DB-level default (would require
        // doctrine/dbal, not installed) — application code always writes an
        // explicit status value, so the stale 'Not Verified' default is only
        // ever relevant if a row were inserted bypassing the model, which
        // nothing in this app does.
    }

    public function down(): void
    {
        DB::table('candidate_verification_items')->where('status', 'waiting_payment')->update(['status' => 'Not Verified']);
        DB::table('candidate_verification_items')->where('status', 'waiting_documents')->update(['status' => 'Pending']);
        DB::table('candidate_verification_items')->where('status', 'documents_submitted')->update(['status' => 'Pending']);
        DB::table('candidate_verification_items')->where('status', 'under_review')->update(['status' => 'Pending']);
        DB::table('candidate_verification_items')->where('status', 'more_information_required')->update(['status' => 'Pending']);
        DB::table('candidate_verification_items')->where('status', 'verified')->update(['status' => 'Verified']);
        DB::table('candidate_verification_items')->where('status', 'partially_verified')->update(['status' => 'Verified']);
        DB::table('candidate_verification_items')->where('status', 'rejected')->update(['status' => 'Rejected']);

        Schema::table('candidate_verification_items', function (Blueprint $table) {
            $table->dropColumn('declaration_accepted_at');
        });
    }
};
