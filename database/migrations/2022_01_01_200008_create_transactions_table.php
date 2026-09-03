<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            // Soft host key: user lives on the host app (indexed via composite below).
            $table->unsignedBigInteger('user_id');
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            // Literal defaults mirror host TransactionTypeEnum::PAYMENT / TransactionStatusEnum::PENDING
            // (no host enum dependency).
            $table->string('type')->default('payment');
            $table->decimal('amount', 15)->default(0);
            $table->string('status')->default('pending')->index();
            $table->string('authority')->nullable()->unique();  // Gateway authority code
            $table->string('ref_id')->nullable();               // Gateway reference ID
            $table->string('tracking_code')->unique();          // Internal tracking code
            $table->string('card_number')->nullable();          // Masked card number
            $table->json('gateway_response')->nullable();       // Raw gateway response
            $table->timestamp('paid_at')->nullable();
            $table->json('extra_attributes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
