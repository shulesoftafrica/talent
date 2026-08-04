<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * For a wallet/usage-type product on the Shulesoft Billing Platform, the
 * UCN (control number) the platform issues is tied to the candidate's
 * customer/wallet record, not to any one invoice — it stays the same across
 * every purchase they ever make. Caching it locally means we can show it
 * immediately on future purchases/renewals without depending on a live API
 * call succeeding, and gives support a stable reference number per
 * candidate. The per-order billing_ucn on verification_orders is untouched
 * (still populated from whatever the API returns for that specific
 * invoice) — this is purely an additional, candidate-level record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->string('billing_ucn')->nullable()->after('premium_until');
        });
    }

    public function down(): void
    {
        Schema::table('candidates', function (Blueprint $table) {
            $table->dropColumn('billing_ucn');
        });
    }
};
