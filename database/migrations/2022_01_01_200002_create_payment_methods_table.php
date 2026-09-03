<?php

declare(strict_types=1);

use App\Enums\BooleanEnum;
use App\Enums\PaymentMethodProviderEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            // translation: title, description
            $table->text('languages')->nullable();
            $table->string('provider')->default(PaymentMethodProviderEnum::CASH->value);
            $table->json('extra_attributes')->nullable();  // API keys, configs, etc.
            $table->boolean('published')->default(BooleanEnum::ENABLE->value);
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
