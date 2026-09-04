<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->morphs('reference');
            // Soft host key: branches live on the host app.
            $table->unsignedBigInteger('branch_id')->index();
            $table->boolean('primary')->default(false)->index();
            $table->json('extra_attributes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['reference_type', 'reference_id', 'branch_id'], 'wallets_reference_branch_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
