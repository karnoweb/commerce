<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Billing ownership: Payment settles an Invoice.
 *
 * - invoice_id is the authoritative billing link (nullable during legacy migration).
 * - order_id remains for backward compatibility / denormalized lookup; nullable so
 *   standalone invoices can receive payments without an Order.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('invoice_id')
                ->nullable()
                ->after('order_id')
                ->constrained('invoices')
                ->nullOnDelete();

            $table->index(['invoice_id', 'status']);
        });

        // Drop NOT NULL + cascade so standalone invoice payments are valid.
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();
            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->nullOnDelete();
        });

        // Best-effort backfill: single deterministic invoice per order.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('
                UPDATE payments
                INNER JOIN (
                    SELECT order_id, MIN(id) AS invoice_id
                    FROM invoices
                    WHERE order_id IS NOT NULL AND deleted_at IS NULL
                    GROUP BY order_id
                    HAVING COUNT(*) = 1
                ) AS single_invoice ON single_invoice.order_id = payments.order_id
                SET payments.invoice_id = single_invoice.invoice_id
                WHERE payments.invoice_id IS NULL
                  AND payments.order_id IS NOT NULL
            ');
        } else {
            $pairs = DB::table('invoices')
                ->select('order_id', DB::raw('MIN(id) as invoice_id'))
                ->whereNotNull('order_id')
                ->whereNull('deleted_at')
                ->groupBy('order_id')
                ->havingRaw('COUNT(*) = 1')
                ->get();

            foreach ($pairs as $pair) {
                DB::table('payments')
                    ->where('order_id', $pair->order_id)
                    ->whereNull('invoice_id')
                    ->update(['invoice_id' => $pair->invoice_id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropIndex(['invoice_id', 'status']);
            $table->dropColumn('invoice_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        // Legacy restore: orphaned standalone payments cannot survive NOT NULL order_id.
        DB::table('payments')->whereNull('order_id')->delete();

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable(false)->change();
            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->cascadeOnDelete();
        });
    }
};
