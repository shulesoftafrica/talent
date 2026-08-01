<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidate_otps', function (Blueprint $table) {
            $table->id();
            $table->string('phone_or_email');
            $table->string('code');
            $table->string('purpose')->default('login'); // login, verify_phone, verify_email
            $table->string('channel')->default('sms'); // sms, whatsapp, email
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();

            $table->index(['phone_or_email', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_otps');
    }
};
