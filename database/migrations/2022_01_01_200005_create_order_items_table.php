<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            // Soft host key: user lives on the host app.
            $table->unsignedBigInteger('user_id')->nullable()->index();
            // Soft cross-domain key: product catalog belongs to karnoweb/shop, not commerce.
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('default_price', 15)->default(0);
            $table->decimal('sale_price', 15)->default(0);
            $table->decimal('discount_amount', 15)->default(0);
            $table->decimal('tax_amount', 15)->default(0);
            $table->json('extra_attributes')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'product_id']);
            $table->index('campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
