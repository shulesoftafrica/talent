<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attribution for ShuleSoft's "Share Vacancy" distribution channels
 * (whatsapp/facebook/linkedin/qr/poster_square/poster_whatsapp/poster_a4/
 * copied_link) — carried on the public vacancy URL's ?ref= query param,
 * through PublicVacancyController -> the OTP verify auto-apply path and
 * ApplicationsController::apply(), into ApplicationService::apply(), and
 * finally stamped here so a school/ShuleSoft can see which channel actually
 * produced a given application.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('source_channel')->nullable()->after('source_application_id');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('source_channel');
        });
    }
};
