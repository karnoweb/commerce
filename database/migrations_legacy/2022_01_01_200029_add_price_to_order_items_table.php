<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OrderItem::booted() mirrors sale_price into `price` (and back) on save,
 * but no migration ever created the column, making any create() with only
 * `sale_price` set fail standalone. Nullable, same precision as sale_price.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('order_items', 'price')) {
                $table->decimal('price', 15)->nullable()->after('sale_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            if (Schema::hasColumn('order_items', 'price')) {
                $table->dropColumn('price');
            }
        });
    }
};
