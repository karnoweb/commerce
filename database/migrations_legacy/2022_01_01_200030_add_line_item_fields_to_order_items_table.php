<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic line items: `product_id` becomes optional (service/text/custom
 * lines have no catalog product) and each line gets an `item_type`
 * (product|service|text|custom) plus an optional `title` for lines that
 * have no product relation to source a display name from. Additive and
 * backward compatible — existing product-only rows default to `product`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('order_items', 'item_type')) {
                $table->string('item_type', 20)->default('product')->after('product_id')->index();
            }

            if (! Schema::hasColumn('order_items', 'title')) {
                $table->string('title')->nullable()->after('item_type');
            }
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id')->change();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            if (Schema::hasColumn('order_items', 'item_type')) {
                $table->dropIndex(['item_type']);
            }
        });

        Schema::table('order_items', function (Blueprint $table): void {
            if (Schema::hasColumn('order_items', 'title')) {
                $table->dropColumn('title');
            }

            if (Schema::hasColumn('order_items', 'item_type')) {
                $table->dropColumn('item_type');
            }
        });
    }
};
