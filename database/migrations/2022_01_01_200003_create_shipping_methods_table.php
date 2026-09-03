<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            // translation: title, description
            $table->text('languages')->nullable();
            // 'standard' mirrors host ShippingMethodDriverEnum::STANDARD (no host enum dependency).
            $table->string('driver')->default('standard');
            $table->decimal('price', 15)->default(0);
            $table->decimal('free_threshold', 15)->nullable();  // Free shipping if order > this
            $table->decimal('min_order_amount', 15)->nullable();
            $table->decimal('max_weight', 10, 2)->nullable();
            $table->unsignedSmallInteger('estimated_days')->nullable();
            $table->unsignedInteger('ordering')->default(0);
            $table->json('extra_attributes')->nullable();
            $table->boolean('published')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
    }
};
