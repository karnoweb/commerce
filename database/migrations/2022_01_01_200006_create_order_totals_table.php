<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_totals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type');  // subtotal, tax, discount, shipping, total
            $table->char('sign', 1)->default('+');  // + or -
            $table->decimal('price', 15)->default(0);
            $table->json('payload')->nullable();  // Additional data like discount code, tax rate, etc.
            $table->softDeletes();
            $table->timestamps();

            $table->index(['order_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_totals');
    }
};
