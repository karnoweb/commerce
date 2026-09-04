<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            // Soft host keys: branch and user live on the host app.
            $table->unsignedBigInteger('branch_id')->default(1)->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->foreignId('order_id')->nullable()->index()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->date('invoice_date');
            $table->string('status', 30)->default('draft')->index();
            $table->text('note')->nullable();
            $table->foreignId('document_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
