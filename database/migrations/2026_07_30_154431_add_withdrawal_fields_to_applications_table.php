<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('withdrawal_reason')->nullable()->after('last_seen_status');
            $table->text('withdrawal_reason_other')->nullable()->after('withdrawal_reason');
            $table->timestamp('withdrawn_at')->nullable()->after('withdrawal_reason_other');

            // Optional "accepted another offer" follow-up — only ever filled
            // in if the candidate chooses to share it; also used to update
            // their profile (see ApplicationWithdrawalController).
            $table->string('new_employer_name')->nullable()->after('withdrawn_at');
            $table->string('new_position')->nullable()->after('new_employer_name');
            $table->date('new_start_date')->nullable()->after('new_position');
            $table->boolean('found_via_shulesoft')->nullable()->after('new_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn([
                'withdrawal_reason', 'withdrawal_reason_other', 'withdrawn_at',
                'new_employer_name', 'new_position', 'new_start_date', 'found_via_shulesoft',
            ]);
        });
    }
};
