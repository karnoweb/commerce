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
| `CartService` | Auth session + `OrderItem` eager-loads `product.productInterface.media` (CMS media) |
| `InvoiceBillingCoordinator` | Dispatches rich host `App\Events\Commerce\InvoiceFullyPaid` (CRM bridge payload) **and** lean `Karnoweb\Commerce\Events\InvoiceFullyPaid` via `CommerceEventDispatcher` |

Revisit packaging order pricing / cart / discount evaluation only after introducing additional contracts.

## Facade (`Commerce`)

**Macroable** manager with config + model resolution:

- `Commerce::config('…')` → commerce config
- `Commerce::model('order')` → configured model class
- `Commerce::macro('openOrders', fn ($userId) => …)` → host-specific helpers

Host coordinators dispatch **both** rich host events (CRM/notifications) and lean package events (`OrderCreated`, `OrderPaid`, `InvoiceFullyPaid`) through `CommerceEventDispatcher`.
