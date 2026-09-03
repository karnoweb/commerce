<?php

declare(strict_types=1);

use App\Enums\TransactionStatusEnum;
use App\Enums\TransactionTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default(TransactionTypeEnum::PAYMENT->value);
            $table->decimal('amount', 15)->default(0);
            $table->string('status')->default(TransactionStatusEnum::PENDING->value)->index();
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
