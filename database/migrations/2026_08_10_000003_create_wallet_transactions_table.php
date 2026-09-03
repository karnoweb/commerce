<?php

declare(strict_types=1);

use App\Enums\SignEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('causer_id')->index()->constrained('users')->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->tinyInteger('sign')->default(SignEnum::POSITIVE->value)->index();
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
