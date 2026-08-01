<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_teaching_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            // References constant.refer_subjects(subject_id) — same physical
            // Postgres database, different schema, so the FK is added via raw
            // SQL below rather than Laravel's single-schema constrained().
            $table->unsignedInteger('subject_id');
            $table->unsignedTinyInteger('years_experience')->default(1);
            $table->timestamps();

            $table->unique(['candidate_id', 'subject_id']);
        });

        DB::statement('
            ALTER TABLE talent.candidate_teaching_subjects
            ADD CONSTRAINT candidate_teaching_subjects_subject_id_foreign
            FOREIGN KEY (subject_id) REFERENCES constant.refer_subjects (subject_id)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_teaching_subjects');
    }
};
