<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verification_order_id')->constrained('verification_orders')->cascadeOnDelete();
            $table->foreignId('candidate_verification_item_id')->constrained('candidate_verification_items')->cascadeOnDelete();
            $table->decimal('price', 8, 2);
            $table->timestamps();

            $table->unique(['verification_order_id', 'candidate_verification_item_id'], 'voi_order_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_order_items');
    }
};
