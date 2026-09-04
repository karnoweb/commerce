<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Karnoweb\Commerce\Support\CommerceTables;

/**
 * Single squashed schema for karnoweb/commerce. Replaces every prior
 * incremental migration (kept, unloaded, under database/migrations_legacy/
 * for reference only — CommerceServiceProvider only loads this file).
 *
 * Design:
 * - Table prefix + configurable names: every table name below is resolved
 *   through CommerceTables::name('<key>') — never a bare string — so a
 *   host can rename the whole schema (or apply a different prefix than the
 *   default `com_`) purely via config/commerce.php, no code changes.
 * - Generic lines: order_lines has no `product_id`. Every line is
 *   item_type + item_id (soft, nullable) + item_name (required snapshot) +
 *   item_sku (optional snapshot) — product/service/text/custom/anything the
 *   host defines, with zero schema changes. line_total_amount is simply
 *   quantity x unit_price_amount; there is no per-line tax/discount column.
 * - Adjustments, not fixed columns: document_adjustments is a flexible +/-
 *   ledger (key/sign/amount/payload), polymorphic over `adjustable`
 *   (Order, Invoice, ...) backing shippingAmount()/taxAmount()/
 *   discountAmount() shortcuts *and* arbitrary custom adjustments (fees,
 *   rounding, coupons). orders/invoices only ever store subtotal/total —
 *   there is no discount_amount/tax_amount/shipping_amount column on
 *   either table; the ledger is the single source of truth.
 * - Dimensions, not fixed columns: document_dimensions is a generic
 *   key/value ledger for reporting dimensions (sales_unit_id,
 *   warehouse_id, channel_id, cashier_id, ...), polymorphic over
 *   `documentable` (Order, Invoice, OrderLine, ...), alongside first-class
 *   sales_unit_id/warehouse_id shortcut columns on orders/invoices for
 *   fast, no-join filtering.
 * - Returns are quantity-based and reference a normalized reason:
 *   order_return_lines.return_reason_id is an internal FK to
 *   return_reasons (seeded via database/seeders/CommerceSeeder.php);
 *   reason_note carries any free-text note alongside it.
 * - Wallets are always branch-scoped: branch_id is NOT NULL, with the
 *   convention that 0 means "global" (branch-agnostic) — never NULL, so
 *   the (reference_type, reference_id, branch_id) unique index behaves
 *   consistently across every database driver.
 * - Every amount column is a bigint (smallest currency unit, no floats).
 * - Every host/cross-domain reference (user_id, branch_id, sales_unit_id,
 *   warehouse_id, item_id) is a soft unsignedBigInteger + index — never a
 *   hard FK. FKs are only ever declared between tables this package
 *   itself owns (orders -> order_lines, order_returns -> order_return_lines,
 *   invoices -> payments -> transactions, order_return_lines -> return_reasons).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->createOrdersTable();
        $this->createOrderLinesTable();
        $this->createDocumentAdjustmentsTable();
        $this->createDocumentDimensionsTable();
        $this->createInvoicesTable();
        $this->createPaymentMethodsTable();
        $this->createShippingMethodsTable();
        $this->createPaymentsTable();
        $this->createTransactionsTable();
        $this->createReturnReasonsTable();
        $this->createOrderReturnsTable();
        $this->createOrderReturnLinesTable();
        $this->createWalletsTable();
        $this->createWalletTransactionsTable();
        $this->createDiscountsTable();
        $this->createDiscountUserGroupTable();
    }

    public function down(): void
    {
        // Children before parents.
        Schema::dropIfExists(CommerceTables::name('discount_user_group'));
        Schema::dropIfExists(CommerceTables::name('discounts'));
        Schema::dropIfExists(CommerceTables::name('wallet_transactions'));
        Schema::dropIfExists(CommerceTables::name('wallets'));
        Schema::dropIfExists(CommerceTables::name('order_return_lines'));
        Schema::dropIfExists(CommerceTables::name('order_returns'));
        Schema::dropIfExists(CommerceTables::name('return_reasons'));
        Schema::dropIfExists(CommerceTables::name('transactions'));
        Schema::dropIfExists(CommerceTables::name('payments'));
        Schema::dropIfExists(CommerceTables::name('shipping_methods'));
        Schema::dropIfExists(CommerceTables::name('payment_methods'));
        Schema::dropIfExists(CommerceTables::name('invoices'));
        Schema::dropIfExists(CommerceTables::name('document_dimensions'));
        Schema::dropIfExists(CommerceTables::name('document_adjustments'));
        Schema::dropIfExists(CommerceTables::name('order_lines'));
        Schema::dropIfExists(CommerceTables::name('orders'));
    }

    private function createOrdersTable(): void
    {
        Schema::create(CommerceTables::name('orders'), function (Blueprint $table): void {
            $table->id();
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('order_number')->nullable()->unique();
            // Soft host keys — no constrained()/foreign() to host tables.
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('sales_unit_id')->nullable()->index();
            $table->unsignedBigInteger('warehouse_id')->nullable()->index();
            $table->string('type')->default('sale')->index();
            $table->string('status')->default('pending')->index();
            // Only subtotal/total are stored — no discount/tax/shipping
            // column: those live exclusively in document_adjustments.
            $table->bigInteger('subtotal_amount')->default(0);
            $table->bigInteger('total_amount')->default(0);
            $table->string('currency', 10)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('extra_attributes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createOrderLinesTable(): void
    {
        Schema::create(CommerceTables::name('order_lines'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained(CommerceTables::name('orders'))->cascadeOnDelete();
            // Soft host keys.
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            // Generic item reference — no product_id, no shop model dependency.
            $table->string('item_type')->index();
            $table->unsignedBigInteger('item_id')->nullable()->index();
            $table->string('item_name');
            $table->string('item_sku')->nullable();
            $table->decimal('quantity', 18, 6)->default(1);
            $table->string('uom_code')->nullable();
            $table->bigInteger('unit_price_amount')->default(0);
            // quantity x unit_price_amount — no per-line tax/discount column.
            $table->bigInteger('line_total_amount')->default(0);
            $table->date('expires_at')->nullable();
            $table->json('extra_attributes')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'item_type']);
        });
    }

    private function createDocumentAdjustmentsTable(): void
    {
        Schema::create(CommerceTables::name('document_adjustments'), function (Blueprint $table): void {
            $table->id();
            // Polymorphic: Order, Invoice, ... — adds an (adjustable_type,
            // adjustable_id) index automatically.
            $table->morphs('adjustable');
            $table->string('key')->index();
            $table->tinyInteger('sign')->default(1);
            $table->unsignedBigInteger('amount')->default(0);
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    private function createDocumentDimensionsTable(): void
    {
        Schema::create(CommerceTables::name('document_dimensions'), function (Blueprint $table): void {
            $table->id();
            // Polymorphic: Order, Invoice, OrderLine, Payment, ... — adds an
            // (documentable_type, documentable_id) index automatically.
            $table->morphs('documentable');
            $table->string('key')->index();
            $table->bigInteger('value_int')->nullable();
            $table->string('value_string')->nullable();
            $table->json('value_json')->nullable();
            $table->timestamps();

            $table->index(['documentable_type', 'key', 'value_int'], 'document_dimensions_type_key_value_int_idx');
        });
    }

    private function createInvoicesTable(): void
    {
        Schema::create(CommerceTables::name('invoices'), function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_number')->nullable()->unique();
            $table->string('idempotency_key')->nullable()->unique();
            // order_id nullable: standalone invoices (no order) are supported.
            $table->foreignId('order_id')->nullable()->index()->constrained(CommerceTables::name('orders'))->nullOnDelete();
            // Soft host keys.
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('sales_unit_id')->nullable()->index();
            $table->unsignedBigInteger('warehouse_id')->nullable()->index();
            // Final amount only — no tax_amount/discount_amount column;
            // any breakdown lives in document_adjustments.
            $table->bigInteger('amount')->default(0);
            $table->date('invoice_date')->nullable();
            $table->string('status')->default('issued')->index();
            $table->json('extra_attributes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createPaymentMethodsTable(): void
    {
        Schema::create(CommerceTables::name('payment_methods'), function (Blueprint $table): void {
            $table->id();
            $table->text('languages')->nullable();
            $table->string('provider')->default('cash');
            $table->json('extra_attributes')->nullable();
            $table->boolean('published')->default(true);
            $table->unsignedInteger('ordering')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createShippingMethodsTable(): void
    {
        Schema::create(CommerceTables::name('shipping_methods'), function (Blueprint $table): void {
            $table->id();
            $table->text('languages')->nullable();
            $table->string('driver')->default('standard');
            $table->bigInteger('price')->default(0);
            $table->bigInteger('free_threshold')->nullable();
            $table->bigInteger('min_order_amount')->nullable();
            $table->decimal('max_weight', 10, 2)->nullable();
            $table->unsignedSmallInteger('estimated_days')->nullable();
            $table->unsignedInteger('ordering')->default(0);
            $table->json('extra_attributes')->nullable();
            $table->boolean('published')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createPaymentsTable(): void
    {
        Schema::create(CommerceTables::name('payments'), function (Blueprint $table): void {
            $table->id();
            $table->string('idempotency_key')->nullable()->unique();
            // Payments always settle an invoice (mandatory billing link).
            $table->foreignId('invoice_id')->index()->constrained(CommerceTables::name('invoices'))->cascadeOnDelete();
            // Denormalized for convenient lookup only — invoice_id is authoritative.
            $table->foreignId('order_id')->nullable()->index()->constrained(CommerceTables::name('orders'))->nullOnDelete();
            // Soft host keys.
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            // Internal package reference (payment_methods is owned by this package too).
            $table->foreignId('payment_method_id')->nullable()->constrained(CommerceTables::name('payment_methods'))->nullOnDelete();
            $table->bigInteger('amount')->default(0);
            $table->string('type')->index();
            $table->string('status')->default('pending')->index();
            $table->text('note')->nullable();
            $table->json('extra_attributes')->nullable();
            $table->timestamps();
        });
    }

    private function createTransactionsTable(): void
    {
        Schema::create(CommerceTables::name('transactions'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->index()->constrained(CommerceTables::name('payments'))->cascadeOnDelete();
            $table->string('gateway')->nullable();
            $table->string('ref_id')->nullable();
            $table->string('tracking_code')->nullable()->unique();
            $table->json('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('extra_attributes')->nullable();
            $table->timestamps();
        });
    }

    private function createReturnReasonsTable(): void
    {
        Schema::create(CommerceTables::name('return_reasons'), function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->boolean('published')->default(true);
            $table->unsignedInteger('ordering')->default(0);
            $table->timestamps();
        });
    }

    private function createOrderReturnsTable(): void
    {
        Schema::create(CommerceTables::name('order_returns'), function (Blueprint $table): void {
            $table->id();
            $table->string('idempotency_key')->nullable()->unique();
            $table->foreignId('order_id')->index()->constrained(CommerceTables::name('orders'))->cascadeOnDelete();
            $table->bigInteger('total_amount')->default(0);
            $table->text('reason')->nullable();
            $table->json('extra_attributes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function createOrderReturnLinesTable(): void
    {
        Schema::create(CommerceTables::name('order_return_lines'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_return_id')->index()->constrained(CommerceTables::name('order_returns'))->cascadeOnDelete();
            $table->foreignId('order_line_id')->index()->constrained(CommerceTables::name('order_lines'))->cascadeOnDelete();
            $table->decimal('quantity', 18, 6);
            $table->bigInteger('unit_price_amount')->default(0);
            $table->bigInteger('amount')->default(0);
            // Normalized reason (internal FK — return_reasons is owned by
            // this package too) + an optional free-text note alongside it.
            $table->foreignId('return_reason_id')->nullable()->constrained(CommerceTables::name('return_reasons'))->nullOnDelete();
            $table->string('reason_note')->nullable();
            $table->timestamps();
        });
    }

    private function createWalletsTable(): void
    {
        Schema::create(CommerceTables::name('wallets'), function (Blueprint $table): void {
            $table->id();
            $table->morphs('reference');
            // Soft host key. Always NOT NULL — convention: 0 = "global"
            // (branch-agnostic wallet), never null, so the unique index
            // below behaves consistently across every database driver.
            $table->unsignedBigInteger('branch_id')->index();
            $table->boolean('primary')->default(false)->index();
            $table->json('extra_attributes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['reference_type', 'reference_id', 'branch_id'], 'wallets_reference_branch_unique');
        });
    }

    private function createWalletTransactionsTable(): void
    {
        Schema::create(CommerceTables::name('wallet_transactions'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_id')->index()->constrained(CommerceTables::name('wallets'))->cascadeOnDelete();
            $table->string('idempotency_key')->nullable()->unique();
            // Soft host key: causer (actor) user lives on the host app.
            $table->unsignedBigInteger('causer_id')->index();
            $table->bigInteger('amount')->default(0);
            $table->tinyInteger('sign')->default(1)->index();
            $table->string('type', 30)->index();
            $table->nullableMorphs('transactionable', 'wallet_transactions_transactionable_index');
            $table->text('description')->nullable();
            $table->boolean('published')->default(true)->index();
            $table->timestamps();
        });
    }

    private function createDiscountsTable(): void
    {
        Schema::create(CommerceTables::name('discounts'), function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique()->index();
            $table->string('type')->default('percentage')->index();
            $table->decimal('value', 10, 2);
            // Soft host key: null = for all users.
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->bigInteger('min_order_amount')->default(0);
            $table->bigInteger('max_discount_amount')->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_per_user')->default(1);
            $table->integer('used_count')->default(0);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    private function createDiscountUserGroupTable(): void
    {
        Schema::create(CommerceTables::name('discount_user_group'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('discount_id')->constrained(CommerceTables::name('discounts'))->cascadeOnDelete();
            // Soft host key: user groups live on the host app.
            $table->unsignedBigInteger('user_group_id');
            $table->timestamps();

            $table->unique(['discount_id', 'user_group_id']);
        });
    }
};
