<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OrderItem::itemable() (morphTo) and its saving() hook — which snapshots
 * `product_id` onto `itemable_type`/`itemable_id` — predate this migration
 * but had no backing columns, making any `product_id`-based create() fail
 * standalone. Nullable polymorphic pair, no FK: soft reference only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('order_items', 'itemable_type')) {
                $table->nullableMorphs('itemable');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            if (Schema::hasColumn('order_items', 'itemable_type')) {
                $table->dropMorphs('itemable');
            }
        });
    }
};
