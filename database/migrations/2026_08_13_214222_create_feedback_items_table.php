<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The single backing table for the whole "Help & Feedback" bubble: every
 * submission — a help request, a like/neutral/dislike opinion, a reported
 * problem, a suggestion, or a quick post-event rating — is one row here,
 * distinguished by category/subcategory rather than separate tables. This
 * is deliberate: the admin-side "product intelligence" dashboard (top
 * problems, sentiment split, most-requested improvements) needs to query
 * across all of them together, and a candidate's "your previous requests"
 * list needs the same.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();

            // help | feedback | problem | idea
            $table->string('category');
            // A fixed, known vocabulary per category — see FeedbackItem::PRIORITY_MAP
            // for the full list; free text lives in `message`, not here, so
            // this stays reliably groupable for the "Top Problems" dashboard.
            $table->string('subcategory')->nullable();
            // like | neutral | dislike — only ever set for category=feedback
            // (both the modal's "what do you think" step and the standalone
            // post-event quick-rating prompt share this same column).
            $table->string('sentiment')->nullable();
            $table->text('message')->nullable();

            // Where the candidate opened the bubble from, e.g. "Applications
            // / Software Developer" or "Profile / Verification" — a human
            // label for staff, not meant to be parsed back apart.
            $table->string('context_label')->nullable();
            $table->string('context_path')->nullable();
            // Only populated when opened from a specific job/application —
            // real FK for application_id since applications lives in this
            // same connection; job posting is a soft cross-schema reference
            // (shulesoft/safaribook), same reasoning as Application::source_job_posting_id.
            $table->foreignId('related_application_id')->nullable()->constrained('applications')->nullOnDelete();
            $table->string('related_source_schema')->nullable();
            $table->unsignedBigInteger('related_job_posting_id')->nullable();

            $table->string('user_agent')->nullable();

            // new -> in_review -> responded -> resolved
            $table->string('status')->default('new');
            // critical | high | normal | feedback — auto-classified from
            // category+subcategory at submission time (FeedbackItem::classifyPriority()),
            // not candidate-chosen.
            $table->string('priority')->default('normal');

            // Soft reference only — admin.users lives in a separate
            // schema/connection than this table, same reasoning as
            // candidate_verification_items.reviewed_by.
            $table->unsignedBigInteger('assigned_officer_id')->nullable();
            $table->text('staff_response')->nullable();
            $table->text('resolution')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['category', 'status']);
            $table->index(['priority', 'status']);
            $table->index('candidate_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_items');
    }
};
