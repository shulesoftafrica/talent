<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a uuid column (used as the route key instead of the sequential
 * integer id — see App\Models\Concerns\HasUuidRouteKey) to every model
 * whose id currently appears in a candidate-facing URL. Existing rows are
 * backfilled via Postgres's native gen_random_uuid() (core since PG13);
 * new rows get theirs from the trait's creating() hook, matching the
 * existing candidates.uuid convention — no DB-level default needed.
 */
return new class extends Migration
{
    private const TABLES = [
        'applications',
        'notifications',
        'verification_orders',
        'candidate_experiences',
        'candidate_educations',
        'candidate_certifications',
        'candidate_skills',
        'candidate_hobbies',
        'candidate_portfolio_items',
        'candidate_teaching_subjects',
        'candidate_verification_references',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->uuid('uuid')->nullable()->after('id');
            });

            DB::statement("UPDATE {$table} SET uuid = gen_random_uuid() WHERE uuid IS NULL");

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->uuid('uuid')->nullable(false)->change();
            });

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unique('uuid');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('uuid');
            });
        }
    }
};
