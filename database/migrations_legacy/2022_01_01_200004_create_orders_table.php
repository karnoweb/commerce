<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Karnoweb\Commerce\Enums\OrderStatusEnum;
use Karnoweb\Commerce\Enums\OrderTypeEnum;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // Soft host key: user lives on the host app, not in this package.
            $table->unsignedBigInteger('user_id')->index();
            $table->decimal('total', 15)->default(0);
            $table->decimal('subtotal', 15)->default(0);
            $table->decimal('discount_amount', 15)->default(0);
            $table->decimal('tax_amount', 15)->default(0);
            $table->decimal('shipping_amount', 15)->default(0);
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shipping_method_id')->nullable()->constrained()->nullOnDelete();
            // Soft host key: addresses live on the host app.
            $table->unsignedBigInteger('address_id')->nullable()->index();
            $table->foreignId('discount_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->string('order_number')->unique()->index();
            $table->json('address')->nullable();
            $table->text('description')->nullable();
            $table->string('type')->default(OrderTypeEnum::SALE->value);
            $table->string('status')->default(OrderStatusEnum::CART->value)->index();
            $table->index('campaign_id');
            $table->text('note')->nullable();
            $table->timestamp('date')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancel_at')->nullable();
            // Soft host keys: actor users live on the host app.
            $table->unsignedBigInteger('cancel_by')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->unsignedBigInteger('delivered_by')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->json('extra_attributes')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
