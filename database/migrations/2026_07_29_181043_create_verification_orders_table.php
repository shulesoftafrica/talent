<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->decimal('total_amount', 8, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending'); // pending, paid, failed, refunded, cancelled

            // Remote Shulesoft Billing Platform references — see
            // App\Services\Billing\ShulesoftBillingClient. Soft references
            // only, since that platform is external to this database.
            $table->string('billing_invoice_id')->nullable();
            $table->string('billing_ucn')->nullable();
            $table->string('billing_stripe_link')->nullable();
            $table->string('billing_flutterwave_link')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_orders');
    }
};
