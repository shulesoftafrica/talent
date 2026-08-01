<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_career_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->string('field_key'); // employmentType, availability, salary, teachLevel, curriculum, examExp, ...
            $table->jsonb('field_value'); // single value or array — uniform for single/multi-select fields
            $table->timestamps();

            $table->unique(['candidate_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_career_answers');
    }
};
