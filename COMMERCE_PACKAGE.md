# COMMERCE PACKAGE — Architecture Contract

> Standalone commerce domain: generic cart/order lines, polymorphic adjustments + reporting dimensions, invoices, payments, wallets, discounts, shipping/payment methods, normalized return reasons. No `Shop` dependency, no `product_id` anywhere.

## Non-negotiables

1. **No `App\Models` imports in `src/`/`tests/`/migrations**
2. **No hard deps** on `crm`, `accounting`, `hr`, `payment`, `inventory`, or `shop` packages
3. **Soft host/cross-domain keys** via `config('commerce.models.*')` only — `user_id`, `branch_id`, `sales_unit_id`, `warehouse_id`, `item_id` are always plain `unsignedBigInteger` + index, never `->constrained()`/`->foreign()`. Only FKs *between Commerce-owned tables* (e.g. `order_return_lines.return_reason_id → return_reasons.id`) are allowed.
4. **Events fire after DB commit** via `CommerceEventDispatcher`
5. **Single squashed schema migration** (`database/migrations_squashed/2026_09_04_000000_create_commerce_schema.php`) — `database/migrations_legacy/*` is historical reference only and is never loaded
6. **Every table is prefixed and individually renameable** — `config('commerce.general.prefix', 'com_')` + `config('commerce.tables.<key>')`, resolved via `Karnoweb\Commerce\Support\CommerceTables::name($key)`. `BaseModel::getTable()` and the squashed migration both go through this helper — never a raw string.
7. Host keeps Actions, Pipelines, Livewire UI; package owns lean models (+ package-safe services only)

## Table prefixing & configurable names

- `config/commerce.php`: `enabled`, `general.prefix` (env `COMMERCE_TABLE_PREFIX`, default `com_`), `tables` (full key→name map, *without* prefix), `models` (per-model class overrides for host subclassing).
- `CommerceTables::name('orders')` → `{prefix}{config('commerce.tables.orders', 'orders')}` (e.g. `com_orders`, or `com_sales_orders` if renamed, or `orders` if prefix is emptied via `COMMERCE_TABLE_PREFIX=`).
- Must be set **before the first `migrate`** — changing it after real data exists requires a manual rename or rollback+remigrate.
- Publish tags: `commerce-config`, `commerce-migrations`, `commerce-lang`, `commerce-seeders`.

## Schema shape (v3 — generic + adjustments/dimensions ledgers)

| Table | Key columns | Notes |
|---|---|---|
| `orders` | `idempotency_key`, `user_id`/`branch_id`/`sales_unit_id`/`warehouse_id` (soft), `type`, `status`, `subtotal_amount`, `total_amount`, `currency` (bigint amounts) | **No** `discount_amount`/`tax_amount`/`shipping_amount` column — those live in `document_adjustments`. `sales_unit_id`/`warehouse_id` are duplicated as a fast, join-free column *and* as `document_dimensions` rows for generic filtering |
| `order_lines` | `order_id` (null = cart line), `item_type` (required, free-form), `item_id` (soft, nullable), `item_name` (required snapshot), `item_sku`, `quantity` (decimal 18,6), `uom_code`, `expires_at`, `unit_price_amount`, `line_total_amount` (bigint) | **No `product_id` column at all.** Any catalog/service/module/text line is `item_type`+`item_id`+`item_name`. `line_total_amount` is always `quantity × unit_price_amount` — no per-line tax/discount |
| `document_adjustments` | `morphs('adjustable')` (`adjustable_type`/`adjustable_id` — `Order`, `Invoice`, or any future document), `key` (`shipping`/`tax`/`discount`/`fee`/`rounding`/`coupon`/custom), `sign` (`+1`/`-1`), `amount` (bigint), `payload` (json) | **Single source of truth** for every fee/tax/discount/shipping line. `total_amount` = `subtotal_amount` + `Σ(sign × amount)`. `HasAdjustments` trait provides `shippingAmount()`/`taxAmount()`/`discountAmount()`/`adjustmentAmount($key)` accessors and an `adjustments()` morphMany |
| `document_dimensions` | `morphs('documentable')` (`Order`, `Invoice`, `OrderLine`, ...), `key` (`sales_unit_id`/`warehouse_id`/`channel_id`/`cashier_id`/custom), `value_int`/`value_string`/`value_json` | Generic reporting/filtering ledger — enables `WHERE key='sales_unit_id' AND value_int IN (1,2)` style queries without schema changes. `HasDimensions` trait provides `addDimension()`/`dimensionValue()`/`dimensions()` |
| `invoices` | `order_id` (nullable — standalone invoices allowed), `idempotency_key`, soft dims, `amount` (bigint, final) | **No** `tax_amount`/`discount_amount` column — `amount` is the single authoritative total; breakdown (if needed) is reported via `document_adjustments` on the invoice. **Mandatory for every order** — `CheckoutService::finalize()` always creates one |
| `payments` | `invoice_id` (**required**), `order_id` (nullable, denormalized), `payment_method_id` (internal FK) | A payment always settles a bill; 1..n payments per invoice are allowed |
| `transactions` | `payment_id` (**required**), `gateway`, `tracking_code`, `gateway_response` (json) | Pure gateway-outcome log; no independent order/user columns |
| `order_returns` / `order_return_lines` | `total_amount` (bigint) / `order_line_id` (required), `quantity` (decimal 18,6), `unit_price_amount`, `amount`, `return_reason_id` (internal FK, nullable), `reason_note` (free text) | Quantity-based returns validated against sold − already-returned per line. Reason is **normalized** via `return_reasons`, not a free string |
| `return_reasons` | `code` (unique), `title`, `published`, `ordering` | Commerce-owned catalog for analytics-friendly return reasons; seeded with `damaged`/`wrong_item`/`not_needed`/`other` |
| `wallets` / `wallet_transactions` | `branch_id` **`NOT NULL`**, convention `0` = "global" (branch-agnostic) | Polymorphic owner, branch-scoped, idempotent. `branch_id` is never `null` — keeps the `(reference_type, reference_id, branch_id)` unique index consistent across every DB driver |
| `discounts`, `discount_user_group`, `payment_methods`, `shipping_methods` | unchanged shape, amounts now bigint | Standalone catalogs a host may reference by soft id; seeded defaults for `payment_methods`/`shipping_methods` |

## Seeders (mandatory)

`database/seeders/CommerceSeeder.php` (publishable via `commerce-seeders` tag, PSR-4 `Karnoweb\Commerce\Database\Seeders\`) idempotently seeds:

- `payment_methods`: `cash`, `card`, `online`, `wallet`
- `shipping_methods`: `standard`, `pickup`
- `return_reasons`: `damaged`, `wrong_item`, `not_needed`, `other`

Run via `php artisan db:seed --class="Karnoweb\Commerce\Database\Seeders\CommerceSeeder"`.

## Boundary

| In commerce | Outside |
|-------------|---------|
| Order, OrderLine, DocumentAdjustment, DocumentDimension, OrderReturn, OrderReturnLine, ReturnReason | Product/service catalog (any host/shop package) |
| Invoice, Payment, Transaction | Accounting Document (host bridge) |
| Wallet, WalletTransaction | CRM Deal, host user/branch models |
| Discount, ShippingMethod, PaymentMethod | Inventory movements, host reporting dimensions (beyond `document_dimensions`) |

## Host extension

Lean models in package. Host subclasses add `CLogsActivity`, accounting `document()` on Invoice, CRM bridges, etc. `config('commerce.models.*')` lets a host swap in its own subclass for every model.

## Canonical package-safe services

Cart → checkout → invoice → payment → refund/returns → wallet as retry-safe, transactional operations, callable via `src/Builders/*` fluent entry points off the `Commerce` facade (`Commerce::cart()`, `checkout()`, `invoices()`, `payment()`/`payments()`, `refund()`, `returns()`, `wallet()`). Full walkthrough: `docs/usage/quickstart.md`.

| Service | Owns | Notes |
|---------|------|-------|
| `CartService` | Cart lines (`OrderLine`, `order_id = null`) | Generic `item_type`/`item_id`/`item_name` — no auth/session/media, no `product_id`. `dimensions` on `LineItemInput` write `document_dimensions` rows on the created line |
| `CheckoutService` | `Order` + `document_adjustments` + `document_dimensions` + mandatory `Invoice` | `finalize()` (alias `place()`) computes `subtotal_amount` from `sum(order_lines.line_total_amount)`, writes `shippingAmount()`/`taxAmount()`/`discountAmount()`/`addAdjustment()` calls as `document_adjustments` rows, `total_amount = subtotal + Σ(sign × amount)`, sets `OrderStatusEnum::PENDING`; idempotent via `orders.idempotency_key`, returns `CheckoutResult { order, invoice }` |
| `InvoiceService` | `Invoice` creation | `createForOrder()` (used internally by checkout, and for attaching an *additional* invoice); `issueStandalone()` for `order_id = null` invoices, accepts optional `adjustments`/`dimensions` arrays for reporting-only breakdown |
| `PaymentService` | `Payment` lifecycle | `initiate()` (PENDING, requires an `Invoice`, idempotent via `payments.idempotency_key`, dispatches `PaymentInitiated`); `confirm()` (→ PAID, creates `Transaction` keyed by `payment_id`, idempotent via `transactions.tracking_code`, dispatches `PaymentConfirmed` + `OrderPaid`/`InvoiceFullyPaid`) |
| `RefundService` | `OrderReturn` + order/payment status (amount-only, legacy) | Always creates a header-only `OrderReturn` (no lines); flips to `REFUNDED` only once returns sum reaches order total; idempotent via `order_returns.idempotency_key` |
| `ReturnService` | `OrderReturn` + `OrderReturnLine` + order/payment status (quantity-based) | Validates each line against sold − already-returned quantity; freezes `unit_price_amount`/`amount` per line from the `OrderLine` snapshot; records `return_reason_id`/`reason_note`; same `REFUNDED` transition rule as `RefundService` (shared `order_returns.total_amount` column); dispatches `ReturnCreated` |
| `WalletService` | `WalletTransaction` credit/debit | Idempotent via `wallet_transactions.idempotency_key`; `branch_id` defaults to `0` ("global") when not specified |

These services never call a gateway, SMS, or mail — they only persist outcomes the host already obtained and dispatch lean events after commit. All idempotency keys are optional, nullable-unique DB columns: retrying with the same key + payload returns the existing record; a different payload throws `Karnoweb\Commerce\Exceptions\IdempotencyConflict`.

### Adjustments & dimensions, not calculator contracts

There is no tax/discount/totals calculator extension point in this schema version — totals are computed directly from `sum(order_lines.line_total_amount)` plus `Σ(sign × amount)` over `document_adjustments`. Hosts compute whatever pricing/tax/discount logic they need and hand Commerce plain numbers via `CheckoutBuilder::shippingAmount()`/`taxAmount()`/`discountAmount()`/`addAdjustment()` — the package never resolves prices itself. Reporting dimensions (`sales_unit_id`, `warehouse_id`, `channel_id`, ...) follow the same pattern via `salesUnitId()`/`warehouseId()`/`addDimension()`, backed by the single polymorphic `document_dimensions` table so new dimensions never require a migration.

## Facade (`Commerce`)

**Macroable** manager with config + model resolution, plus fluent builder entry points:

- `Commerce::config('…')` → commerce config
- `Commerce::model('order')` → configured model class
- `Commerce::macro('openOrders', fn ($userId) => …)` → host-specific helpers
- `Commerce::cart()` / `checkout()` / `invoices()` / `payment()` / `payments()` (alias) / `refund()` / `returns()` / `wallet()` → fresh `Builders\*Builder` instance per call

Host coordinators dispatch **both** rich host events (CRM/notifications) and lean package events (`OrderCreated`, `InvoiceIssued`, `PaymentInitiated`, `PaymentConfirmed`, `OrderPaid`, `InvoiceFullyPaid`, `RefundCreated`, `ReturnCreated`) through `CommerceEventDispatcher`. The canonical services above dispatch the lean events themselves after commit; hosts only need to also fire their rich counterparts if they want the CRM/notification bridge.
