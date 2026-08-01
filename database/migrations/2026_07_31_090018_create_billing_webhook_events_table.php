<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_webhook_events', function (Blueprint $table) {
            $table->id();
            // Idempotency key — invoice_id (preferred) or transaction_id.
            $table->string('idempotency_key');
            $table->string('event_type')->nullable();
            $table->json('payload');
            $table->string('signature')->nullable();
            $table->string('source_ip')->nullable();
            $table->string('processing_status')->default('processing'); // processing|success|failed|unresolved
            $table->text('error_message')->nullable();
            $table->foreignId('verification_order_id')->nullable()->constrained('verification_orders')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['idempotency_key', 'event_type'], 'billing_webhook_events_key_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_webhook_events');
    }
};
