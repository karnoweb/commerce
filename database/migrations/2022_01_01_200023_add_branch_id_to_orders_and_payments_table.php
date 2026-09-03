<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // Soft host key: branches live on the host app.
            $table->unsignedBigInteger('branch_id')->nullable()->after('user_id');
            $table->index('branch_id');
        });

        Schema::table('payments', function (Blueprint $table): void {
            // Soft host key: branches live on the host app.
            $table->unsignedBigInteger('branch_id')->nullable()->after('user_id');
            $table->index('branch_id');
        });

        // Backfill is a best-effort host integration: skip entirely when the host
        // hasn't provided a "branches" table (e.g. standalone package install/tests).
        if (! Schema::hasTable('branches')) {
            return;
        }

        $defaultBranchId = DB::table('branches')->where('is_default', true)->value('id')
            ?? DB::table('branches')->orderBy('id')->value('id');

        if ($defaultBranchId === null) {
            return;
        }

        DB::table('invoices')
            ->whereNotNull('order_id')
            ->whereNotNull('branch_id')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $invoice) use ($defaultBranchId): void {
                DB::table('orders')
                    ->where('id', $invoice->order_id)
                    ->whereNull('branch_id')
                    ->update(['branch_id' => $invoice->branch_id ?? $defaultBranchId]);
            });

        DB::table('invoices')
            ->whereNotNull('branch_id')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $invoice) use ($defaultBranchId): void {
                DB::table('payments')
                    ->where('invoice_id', $invoice->id)
                    ->whereNull('branch_id')
                    ->update(['branch_id' => $invoice->branch_id ?? $defaultBranchId]);
            });

        DB::table('orders')
            ->whereNotNull('branch_id')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $order) use ($defaultBranchId): void {
                DB::table('payments')
                    ->where('order_id', $order->id)
                    ->whereNull('branch_id')
                    ->update(['branch_id' => $order->branch_id ?? $defaultBranchId]);
            });

        DB::table('orders')->whereNull('branch_id')->update(['branch_id' => $defaultBranchId]);
        DB::table('payments')->whereNull('branch_id')->update(['branch_id' => $defaultBranchId]);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['branch_id']);
            $table->dropColumn('branch_id');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['branch_id']);
            $table->dropColumn('branch_id');
        });
    }
};
