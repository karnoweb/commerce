<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quantity-based return lines. Each row references its original sale line
 * (`order_items`) and the return header (`order_returns`) — both owned by
 * this package, so a real FK is fine here (this is not a host/shop table).
 * `unit_price_snapshot`/`amount` freeze the price at return time so later
 * price changes on the catalog never retroactively change history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_return_id')->index()->constrained('order_returns')->cascadeOnDelete();
            $table->foreignId('order_item_id')->index()->constrained('order_items')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price_snapshot', 15)->default(0);
            $table->decimal('amount', 15)->default(0);
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_return_items');
    }
};
