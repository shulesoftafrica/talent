<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->string('source_schema'); // shulesoft | safaribook
            $table->unsignedBigInteger('source_job_posting_id');
            // Soft reference — points at shulesoft.applications.id or
            // safaribook.applications.id depending on source_schema, so a
            // single FK constraint isn't possible here. The reverse column
            // (talent_application_id) on those tables IS a real FK back to
            // this table — see the two add_talent_application_id_to_*
            // migrations that follow.
            $table->unsignedBigInteger('source_application_id')->nullable();

            // Cached only to detect status *changes* for notifications —
            // never used for display. Display always re-reads the origin
            // table's status live via source_application_id.
            $table->string('last_seen_status')->nullable();

            $table->unsignedTinyInteger('ai_health_score')->default(0);
            $table->string('ai_health_label')->nullable();
            $table->json('ai_health_why')->nullable();
            $table->string('ai_health_missing')->nullable();

            $table->date('interview_date')->nullable();
            $table->string('interview_type')->nullable();
            $table->string('interview_duration')->nullable();

            $table->timestamp('applied_at')->useCurrent();
            $table->timestamps();

            $table->index(['candidate_id', 'source_schema']);
            $table->unique(['source_schema', 'source_application_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
