<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DB-level backstop for the duplicate-invoice bug fixed in
 * PaymentService::purchase() (app-level reuse check): two near-simultaneous
 * requests could both pass that check before either had committed its
 * insert, still producing two pending orders -- and two billing-platform
 * invoices -- for the same purchase (confirmed live: two identical Talent
 * Subscription invoices for one candidate, created back to back). A partial
 * unique index makes a second identical pending order impossible at the
 * database layer, not just discouraged in application code.
 *
 * Scoped to (candidate_id, kind, total_amount, currency) rather than just
 * (candidate_id, kind) so a candidate can still have two genuinely different
 * pending orders of the same kind at once -- e.g. two verification-item
 * baskets with different totals -- only a truly identical duplicate is
 * blocked.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS uq_verification_orders_one_pending_per_purchase
            ON verification_orders (candidate_id, kind, total_amount, currency)
            WHERE status = 'pending'
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_verification_orders_one_pending_per_purchase');
    }
};
