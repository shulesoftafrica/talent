<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a nullable reference column (+ real FK) to the existing
 * shulesoft.applications table so a Talent Network application can be
 * linked to its write-through row there, without duplicating any of that
 * table's data — see talent.applications for the rich, single source of
 * truth. Additive only: nullable, no default, nothing existing changes
 * behavior for shulesoft_newversion or any other consumer of that table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('shulesoft')->table('applications', function (Blueprint $table) {
            $table->foreignId('talent_application_id')->nullable()->after('id');
        });

        DB::connection('shulesoft')->statement('
            ALTER TABLE shulesoft.applications
            ADD CONSTRAINT applications_talent_application_id_foreign
            FOREIGN KEY (talent_application_id) REFERENCES talent.applications (id) ON DELETE SET NULL
        ');
    }

    public function down(): void
    {
        Schema::connection('shulesoft')->table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('talent_application_id');
        });
    }
};
