<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Structured skill requirements for a vacancy (spec §33, §47).
 *
 * Job postings live in external schemas (shulesoft/safaribook) as free text, so
 * this table overlays machine-readable "required / preferred skill + minimum
 * level" onto a posting by (source_schema, job_posting_id). The match engine
 * (VacancySkillMatcher) compares these against the candidate's Academy-verified
 * skills to produce the "N of M matched / % match" explanation (§34–§35, §48).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vacancy_skill_requirements', function (Blueprint $table) {
            $table->id();
            $table->string('source_schema');            // shulesoft | safaribook
            $table->unsignedBigInteger('job_posting_id');
            $table->string('skill_slug');               // matches academy.skills.slug
            $table->string('skill_name');
            $table->unsignedTinyInteger('min_level_rank')->default(1); // 1..5 (Foundation..Expert)
            $table->string('min_level_name')->nullable();
            $table->string('requirement')->default('required');        // required | preferred
            $table->timestamps();
            $table->index(['source_schema', 'job_posting_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacancy_skill_requirements');
    }
};
