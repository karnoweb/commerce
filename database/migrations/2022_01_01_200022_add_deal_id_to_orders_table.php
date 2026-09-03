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
            // Soft reference to CRM Deal id (no cross-package FK).
            $table->unsignedBigInteger('deal_id')->nullable()->after('user_id');
            $table->unique('deal_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['deal_id']);
            $table->dropColumn('deal_id');
        });
    }
};
