<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('full_name');
            $table->string('email')->nullable()->unique();
            $table->string('phone')->unique();
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('cv_path')->nullable();
            $table->longText('cv_raw_text')->nullable();
            $table->timestamp('cv_parsed_at')->nullable();
            $table->date('dob')->nullable();
            $table->string('sex')->nullable();
            $table->unsignedInteger('country_id')->nullable();
            $table->string('current_location')->nullable();
            $table->string('profession')->nullable();
            $table->string('current_employer')->nullable();
            $table->boolean('current_employer_verified')->default(false);
            $table->string('availability')->default('Available Immediately');
            $table->boolean('open_to_opportunities')->default(true);
            $table->boolean('is_premium')->default(false);
            $table->timestamp('premium_until')->nullable();
            $table->unsignedTinyInteger('trust_score')->default(0);
            $table->unsignedTinyInteger('profile_strength')->default(0);
            $table->unsignedTinyInteger('career_score')->default(0);
            $table->string('status')->default('active');
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('profession');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
