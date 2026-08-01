<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the same orders table also represent premium subscription purchases
 * (which have no verification_order_items), rather than a near-duplicate
 * table for one extra product.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_orders', function (Blueprint $table) {
            $table->string('kind')->default('verification')->after('candidate_id'); // verification | premium
        });
    }

    public function down(): void
    {
        Schema::table('verification_orders', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
