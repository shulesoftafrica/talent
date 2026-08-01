<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Same as add_talent_application_id_to_shulesoft_applications, for the
 * non-school clients' recruitment schema. Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('safaribook')->table('applications', function (Blueprint $table) {
            $table->foreignId('talent_application_id')->nullable()->after('id');
        });

        DB::connection('safaribook')->statement('
            ALTER TABLE safaribook.applications
            ADD CONSTRAINT applications_talent_application_id_foreign
            FOREIGN KEY (talent_application_id) REFERENCES talent.applications (id) ON DELETE SET NULL
        ');
    }

    public function down(): void
    {
        Schema::connection('safaribook')->table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('talent_application_id');
        });
    }
};
