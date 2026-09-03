<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'order_date')) {
                $table->date('order_date')->nullable()->after('status');
            }
            if (! Schema::hasColumn('orders', 'extra_attributes')) {
                $table->schemalessAttributes('extra_attributes')->after('note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'order_date')) {
                $table->dropColumn('order_date');
            }
            if (Schema::hasColumn('orders', 'extra_attributes')) {
                $table->dropColumn('extra_attributes');
            }
        });
    }
};
