<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->index()->constrained()->cascadeOnDelete();
            // Soft host key: causer (actor) user lives on the host app.
            $table->unsignedBigInteger('causer_id')->index();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->unsignedBigInteger('amount');
            // 1 mirrors host SignEnum::POSITIVE (no host enum dependency).
            $table->tinyInteger('sign')->default(1)->index();
            $table->string('type', 20)->index();
            $table->nullableMorphs('transactionable', 'wallet_transactions_transactionable_index');
            $table->text('description')->nullable();
            $table->boolean('published')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
