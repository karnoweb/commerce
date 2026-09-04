<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retry-safe wallet credit/debit: nullable idempotency key with a unique index.
 * Distinct from the pre-existing nullable-unique `reference` column, which
 * legacy host callers may still populate directly on the model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table): void {
            if (! Schema::hasColumn('wallet_transactions', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->after('reference');
                $table->unique('idempotency_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table): void {
            if (Schema::hasColumn('wallet_transactions', 'idempotency_key')) {
                $table->dropUnique(['idempotency_key']);
                $table->dropColumn('idempotency_key');
            }
        });
    }
};
