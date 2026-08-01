<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_teaching_subject_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_teaching_subject_id')
                ->constrained('candidate_teaching_subjects')
                ->cascadeOnDelete();
            // References constant.refer_classes(id) — cross-schema FK added below.
            $table->unsignedInteger('refer_class_id');
            $table->timestamps();

            $table->unique(['candidate_teaching_subject_id', 'refer_class_id'], 'cts_class_unique');
        });

        DB::statement('
            ALTER TABLE talent.candidate_teaching_subject_classes
            ADD CONSTRAINT candidate_teaching_subject_classes_refer_class_id_foreign
            FOREIGN KEY (refer_class_id) REFERENCES constant.refer_classes (id)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_teaching_subject_classes');
    }
};
