<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            // translation: title, description
            $table->text('languages')->nullable();
            // 'cash' mirrors host PaymentMethodProviderEnum::CASH (no host enum dependency).
            $table->string('provider')->default('cash');
            $table->json('extra_attributes')->nullable();  // API keys, configs, etc.
            $table->boolean('published')->default(true);
            $table->unsignedInteger('ordering')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
