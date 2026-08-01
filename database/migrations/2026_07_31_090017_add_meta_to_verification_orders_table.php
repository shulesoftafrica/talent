<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_orders', function (Blueprint $table) {
            // Generic per-purchase payload for whatever the `kind` column's
            // fulfillment handler needs — e.g. {"months": 1} for premium,
            // {"storage_mb": 5120} for a future storage-limit purchase. This
            // table is the single generic "payment order" record for every
            // paid feature in the app (see App\Services\Billing\PaymentService),
            // not just verification — the name is legacy from when it was
            // verification-only.
            $table->json('meta')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('verification_orders', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
