# سفارش و سبد (مدل‌محور — پیشرفته)

> برای جریان کانونیک با idempotency و انتقال وضعیت خودکار، از `Commerce::cart()`/`Commerce::checkout()` استفاده کنید — [quickstart.md](quickstart.md). این سند همان کار را روی مدل‌ها به‌صورت خام نشان می‌دهد؛ فقط برای درک ساختار جدول‌ها یا کوئری‌های سفارشی مفید است.

```php
use Karnoweb\Commerce\Enums\FinancialStatusEnum;
use Karnoweb\Commerce\Enums\OrderTypeEnum;
use Karnoweb\Commerce\Facades\Commerce;

$Order = Commerce::model('order');
$OrderLine = Commerce::model('order_line');

// خط سبد (بدون order_id) — ارجاع ژنریک، بدون product_id.
// line_total_amount همیشه quantity x unit_price_amount است — بدون
// ستون تخفیف/مالیات در سطح خط.
$OrderLine::query()->create([
    'user_id' => $userId,
    'item_type' => 'shop.product',
    'item_id' => $productId,   // soft، nullable
    'item_name' => 'Coffee Beans 1kg',
    'quantity' => 2,
    'unit_price_amount' => 500_000,
    'line_total_amount' => 1_000_000,
]);

$cart = $OrderLine::query()->carts()->where('user_id', $userId)->get();

// orders فقط subtotal_amount/total_amount را ذخیره می‌کند — هیچ ستون
// discount_amount/tax_amount/shipping_amount ای وجود ندارد؛ آن‌ها فقط
// از طریق document_adjustments (پایین) ثبت می‌شوند.
$order = $Order::query()->create([
    'order_number' => 'ORD-1001',
    'user_id' => $userId,
    'financial_status' => FinancialStatusEnum::PENDING,
    'type' => OrderTypeEnum::SALE,
    'subtotal_amount' => 1_000_000,
    'total_amount' => 1_050_000,
]);

// اتصال سبد به سفارش
$OrderLine::query()
    ->carts()
    ->where('user_id', $userId)
    ->update(['order_id' => $order->id]);

// شکست مبلغ (shipping/tax/discount/هر کلید دلخواه) به‌عنوان یک ردیف
// document_adjustments — رابطهٔ polymorphic روی order (یا invoice).
$order->adjustments()->create(['key' => 'shipping', 'sign' => 1, 'amount' => 50_000]);

$order->shippingAmount(); // 50000 — accessor محاسبه‌شده، نه ستون DB
```

## قوانین

- ساخت سفارش «کامل» (قیمت‌گذاری، تخفیف، موجودی) را در Action میزبان نگه دارید؛ مدل فقط persistence است.
- هیچ `product_id` ستونی در `order_lines` نیست — ارجاع همیشه `item_type`+`item_id` (soft) با اسنپ‌شات `item_name` است.
- هیچ `discount_amount`/`tax_amount`/`shipping_amount` ستونی روی `orders`/`invoices` نیست — `document_adjustments` منبع واحد حقیقت است ([architecture.md](../concepts/architecture.md)).
- `financial_status` را سرویس‌های پکیج جلو می‌برند (confirm / return / cancel)؛ چرخهٔ مجاز سخت است. `workflow_status` برچسب آزاد میزبان است (`Commerce::orders()->setWorkflowStatus()`).

## خطاها

پکیج استثناهای lifecycle مثل HR ندارد؛ اعتبارسنجی را در FormRequest/Action میزبان بنویسید. برای مسیر Facade، استثناهای دامنه در [quickstart.md](quickstart.md) لیست شده‌اند.

## نتیجه ذخیره‌شده

ردیف `orders` و به‌روزرسانی `order_id` روی خطوط سبد. روابط `lines`، `adjustments`، `dimensions`، `invoices`، `payments`، `returns` بعداً پر می‌شوند.
