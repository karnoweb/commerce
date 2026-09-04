<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retry-safe refund processing: nullable idempotency key with a unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_returns', function (Blueprint $table): void {
            if (! Schema::hasColumn('order_returns', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->after('reason');
                $table->unique('idempotency_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_returns', function (Blueprint $table): void {
            if (Schema::hasColumn('order_returns', 'idempotency_key')) {
                $table->dropUnique(['idempotency_key']);
                $table->dropColumn('idempotency_key');
            }
        });
    }
};
