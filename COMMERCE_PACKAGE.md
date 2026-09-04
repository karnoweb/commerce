# COMMERCE PACKAGE — Architecture Contract

> Companion to `karnoweb/shop` (catalog). Owns orders, invoices, payments, wallets, discounts, shipping/payment methods.

## Non-negotiables

1. **No `App\Models` imports in `src/`**
2. **No hard deps** on `crm`, `accounting`, `hr`, `payment`, `inventory`, or `shop` packages
3. **Soft host/shop keys** via `config('commerce.models.*')` only
4. **Events fire after DB commit** via `CommerceEventDispatcher`
5. **Host keeps** Actions, Pipelines, Livewire UI; package owns lean models (+ package-safe services only)

## Boundary

| In commerce | Outside |
|-------------|---------|
| Order, OrderItem, OrderTotal, OrderReturn | Product catalog (shop) |
| Invoice, Payment, Transaction | Accounting Document (host bridge) |
| Wallet, WalletTransaction | CRM Deal (soft deal_id) |
| Discount, ShippingMethod, PaymentMethod | Inventory movements |

## Host extension

Lean models in package. Host subclasses add `CLogsActivity`, accounting `document()` on Invoice, CRM bridges, etc.

## Services — stay in host (this pass)

These remain under `App\Services\*` because each hard-depends on host/CMS/shop surfaces that would fail `NoHostDependencyTest`:

| Service | Why not packaged |
|---------|------------------|
| `OrderPricingService` | `App\Models\{User,Product,Campaign,Discount}`, shop `ProductPriceResolver`, host `CampaignEvaluatorService` |
| `DiscountEvaluatorService` | Host `User` + Discount scopes that assume host user/group models |
| `InvoiceBillingCoordinator` | Dispatches rich host `App\Events\Commerce\InvoiceFullyPaid` (CRM bridge payload) **and** lean `Karnoweb\Commerce\Events\InvoiceFullyPaid` via `CommerceEventDispatcher` |

Revisit packaging order pricing / discount evaluation only after introducing additional contracts. Pricing/discount *inputs* (unit price, tax, discount amounts) are computed by the host and handed to the package's canonical services below as plain numbers — the package never resolves prices itself.

> `App\Services\CartService` above is the **host's** rich, session/CMS-aware cart (auth-bound, eager-loads product media). It is a different class from the package's `Karnoweb\Commerce\Services\CartService` (see below), which only persists/reads cart lines (`OrderItem` rows with `order_id = null`) given an explicit `$userId` — no session, no CMS eager-loading. A host may keep its own `CartService` for UI concerns and still delegate final persistence to the package one, or use the package one directly.

## Canonical package-safe services (new, additive)

Cart → checkout → payment → refund/returns → wallet as retry-safe, transactional operations, callable via `src/Builders/*` fluent entry points off the `Commerce` facade (`Commerce::cart()`, `checkout()`, `payment()`, `refund()`, `returns()`, `wallet()`). Full walkthrough: `docs/usage/quickstart.md`.

| Service | Owns | Notes |
|---------|------|-------|
| `CartService` | Cart lines (`OrderItem`, `order_id = null`) — product/service/text/custom | No auth/session/media — `$userId`, `productId`, `itemableType/Id` are explicit soft references |
| `CheckoutService` | `Order` creation from cart + optional `Invoice` | `placeFromCart()` sets `OrderStatusEnum::PENDING`; idempotent via `orders.idempotency_key`; totals computed via `TotalsCalculatorContract`, order-level tax/discount via `TaxCalculatorContract`/`DiscountCalculatorContract` when the caller omits an explicit amount |
| `PaymentService` | `Payment` lifecycle | `initiate()` (PENDING, idempotent via `payments.idempotency_key`, dispatches `PaymentInitiated`); `confirm()` (→ PAID, creates `Transaction`, idempotent via `transactions.tracking_code`) |
| `RefundService` | `OrderReturn` + order/payment status (amount-only, legacy) | Always creates `OrderReturn`; flips to `REFUNDED` only once returns sum reaches order total; idempotent via `order_returns.idempotency_key` |
| `ReturnService` | `OrderReturn` + `OrderReturnItem` + order/payment status (quantity-based) | Validates each line against sold − already-returned quantity; snapshots `unit_price_snapshot`/`amount` per line; same `REFUNDED` transition rule as `RefundService` (shared `order_returns.amount` column); dispatches `ReturnCreated` |
| `WalletService` | `WalletTransaction` credit/debit | Idempotent via `wallet_transactions.idempotency_key` |

These services never call a gateway, SMS, or mail — they only persist outcomes the host already obtained and dispatch lean events after commit. All idempotency keys are optional, nullable-unique DB columns (Option A from the mission spec): retrying with the same key + payload returns the existing record; a different payload throws `Karnoweb\Commerce\Exceptions\IdempotencyConflict`.

### Tax/Discount/Totals extension points (`src/Contracts/*`)

`TaxCalculatorContract`, `DiscountCalculatorContract`, `TotalsCalculatorContract` are bound to no-op defaults (`Support\Calculators\Null*Calculator`, `DefaultTotalsCalculator`: sum lines + shipping − discount + tax). Hosts override by rebinding the interface in their own service provider — no package changes needed:

```php
$this->app->bind(TaxCalculatorContract::class, MyVatCalculator::class);
```

`CheckoutService` only asks the bound calculator to compute tax/discount when the caller omits `taxAmount()`/`discountAmount()` entirely; an explicit amount (including `0`) always wins.

## Facade (`Commerce`)

**Macroable** manager with config + model resolution, plus fluent builder entry points:

- `Commerce::config('…')` → commerce config
- `Commerce::model('order')` → configured model class
- `Commerce::macro('openOrders', fn ($userId) => …)` → host-specific helpers
- `Commerce::cart()` / `checkout()` / `payment()` / `refund()` / `returns()` / `wallet()` → fresh `Builders\*Builder` instance per call (see above)

Host coordinators dispatch **both** rich host events (CRM/notifications) and lean package events (`OrderCreated`, `OrderPaid`, `InvoiceFullyPaid`, `RefundCreated`, `PaymentInitiated`, `ReturnCreated`) through `CommerceEventDispatcher`. The canonical services above dispatch the lean events themselves after commit; hosts only need to also fire their rich counterparts if they want the CRM/notification bridge.
