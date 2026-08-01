<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('profession')->nullable(); // null = applies to every profession
            $table->string('priority_label')->nullable(); // e.g. HIGH PRIORITY, RECOMMENDED, GROWING DEMAND
            $table->text('why')->nullable();
            $table->string('duration')->nullable();
            $table->string('organizer')->nullable();
            $table->string('price_label')->default('FREE');
            $table->boolean('issues_certificate')->default(true);
            $table->string('next_training_date')->nullable();
            $table->unsignedInteger('seats_available')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainings');
    }
};
