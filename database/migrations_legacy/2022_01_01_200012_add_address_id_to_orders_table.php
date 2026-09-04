<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'address_id')) {
                // Soft host key: addresses live on the host app.
                $table->unsignedBigInteger('address_id')
                    ->nullable()
                    ->after('user_id')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'address_id')) {
                $table->dropIndex(['address_id']);
                $table->dropColumn('address_id');
            }
        });
    }
};
