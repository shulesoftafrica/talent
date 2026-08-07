<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('interview_response')->nullable()->after('interview_duration');
            $table->text('interview_response_note')->nullable()->after('interview_response');
            $table->timestamp('interview_responded_at')->nullable()->after('interview_response_note');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['interview_response', 'interview_response_note', 'interview_responded_at']);
        });
    }
};
