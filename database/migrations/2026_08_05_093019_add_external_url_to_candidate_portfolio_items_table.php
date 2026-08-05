<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidate_portfolio_items', function (Blueprint $table) {
            // For 'Teaching Video' items — a link (e.g. YouTube/Vimeo) instead
            // of an uploaded file. Video file uploads are intentionally not
            // supported (see UploadSecurityService/ProfileItemController).
            $table->string('external_url')->nullable()->after('file_size_bytes');
        });
    }

    public function down(): void
    {
        Schema::table('candidate_portfolio_items', function (Blueprint $table) {
            $table->dropColumn('external_url');
        });
    }
};
