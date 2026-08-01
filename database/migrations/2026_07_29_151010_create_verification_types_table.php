<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_types', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // identity, education, professional_license, employment_history, background_check, references
            $table->string('name');
            $table->string('default_payer')->default('candidate'); // candidate, school, referee
            $table->decimal('default_price', 8, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_types');
    }
};
