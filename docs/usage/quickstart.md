# شروع سریع — Facade سرتاسری

این سند مسیر کامل «سبد → سفارش → فاکتور → پرداخت → مرجوعی» را فقط با فاساد `Commerce` نشان می‌دهد؛ بدون `Order::create` دستی، بدون `update(['status' => ...])` پخش‌شده در کد میزبان. پکیج فقط persistence و رویدادهای lean را انجام می‌دهد — **به درگاه/SMS/میل وصل نمی‌شود**؛ نتیجهٔ نهایی درگاه را میزبان به `Commerce::payment()->confirm()` گزارش می‌کند.

```php
use Karnoweb\Commerce\Facades\Commerce;
use Karnoweb\Commerce\Enums\PaymentTypeEnum;

$userId = 9001;
$branchId = 3;

$itemSnapshot = [
    'title' => 'Coffee Beans 1kg',
    'sku' => 'COF-1KG',
    'price_source' => 'user_group',
    'campaign_id' => null,
];

// 1) افزودن به سبد — OrderItem با order_id = null. productId فقط یک کلید soft است.
Commerce::cart()
    ->forUser($userId)
    ->branchId($branchId)
    ->addItem(
        productId: 501,          // ارجاع soft به shop (بدون FK)
        quantity: 2,
        unitPrice: 1_000_000,
        extra: $itemSnapshot,
    );

// 2) ثبت سفارش از روی سبد — Order می‌سازد، اقلام سبد را متصل می‌کند،
//    و OrderCreated را بعد از commit ارسال می‌کند.
$order = Commerce::checkout()
    ->forUser($userId)
    ->branchId($branchId)
    ->shippingAmount(50_000)
    ->idempotencyKey('checkout:user:9001:cart:active')
    ->place();

// 3) فاکتور اختیاری و package-safe.
$invoice = Commerce::checkout()
    ->forOrder($order)
    ->createInvoice(invoiceNumber: 'INV-1');

// 4) شروع پرداخت — Payment(PENDING) می‌سازد. میزبان بعداً به درگاه وصل می‌شود.
$payment = Commerce::payment()
    ->forOrder($order)
    ->forInvoice($invoice)
    ->methodId(1)
    ->type(PaymentTypeEnum::ONLINE)
    ->amount((int) $order->total)
    ->idempotencyKey('pay:order:'.$order->id.':attempt:1')
    ->initiate();

// 5) تأیید نتیجهٔ درگاه که میزبان گرفته است.
//    Payment(PAID)، Order(PAID)، Invoice(paid)، Transaction می‌سازد،
//    و OrderPaid + InvoiceFullyPaid را بعد از commit ارسال می‌کند.
Commerce::payment()->confirm(
    payment: $payment,
    gateway: 'zarinpal',           // فقط رشته
    refId: 'REF-123',
    trackingCode: 'TRK-777',
    paidAt: now(),
    gatewayPayload: ['raw' => '...'],
);

// 6) مرجوعی جزئی به کیف پول.
//    همیشه OrderReturn می‌سازد؛ اگر مجموع مرجوعی‌ها به کل سفارش برسد،
//    Order → REFUNDED و پرداخت‌های PAID → REFUNDED می‌شوند؛ در غیر این
//    صورت Order روی PAID می‌ماند (بر اساس مجموع OrderReturn محاسبه می‌شود).
Commerce::refund()
    ->forOrder($order)
    ->amount(1_000_000)
    ->reason('customer_return')
    ->toWallet(userId: $userId, branchId: $branchId)
    ->idempotencyKey('refund:order:'.$order->id.':amount:1000000')
    ->process();
```

## بی‌درنگی (idempotency)

`place()`، `initiate()`/`confirm()` و `process()` همگی `idempotencyKey(string $key)` اختیاری می‌پذیرند:

- کلید تکراری با همان payload → همان رکورد قبلی برگردانده می‌شود (retry امن).
- کلید تکراری با payload متفاوت (مثلاً user/order/amount دیگر) → `Karnoweb\Commerce\Exceptions\IdempotencyConflict` پرتاب می‌شود.
- `confirm()` کلید idempotency خودش را نمی‌گیرد؛ اما فراخوانی دوباره با همان `trackingCode` روی پرداخت PAID، بدون ساخت `Transaction` تکراری، همان `Payment` را برمی‌گرداند. `trackingCode` متفاوت روی پرداخت PAID، `CannotConfirmAlreadyPaidPayment` پرتاب می‌کند.

ستون‌های `idempotency_key` روی `orders`، `payments`، `order_returns` و `wallet_transactions` نال‌پذیر و یکتا هستند؛ رکوردهای قدیمی بدون این کلید مشکلی ندارند.

## خطاهای دامنه (جدید)

| استثنا | چه زمانی |
|--------|----------|
| `CannotCheckoutEmptyCart` | `checkout()->place()` وقتی سبد کاربر خالی است |
| `CannotPayCancelledOrder` | `payment()->initiate()` روی سفارش لغوشده |
| `CannotConfirmAlreadyPaidPayment` | `payment()->confirm()` دوباره روی پرداخت PAID با `trackingCode` متفاوت |
| `RefundAmountExceedsPaidAmount` | `refund()->process()` وقتی مبلغ درخواستی + مرجوعی‌های قبلی از کل سفارش بیشتر شود |
| `IdempotencyConflict` | کلید idempotency تکراری با payload متفاوت |

همهٔ این‌ها در `Karnoweb\Commerce\Exceptions\*` هستند و از `RuntimeException` ارث می‌برند.

## نتیجهٔ ذخیره‌شده

- `cart()->addItem()`: یک ردیف `order_items` با `order_id = null`.
- `checkout()->place()`: ردیف `orders` (status=pending) + به‌روزرسانی `order_id` روی اقلام سبد.
- `checkout()->createInvoice()`: ردیف `invoices` (status=issued).
- `payment()->initiate()`: ردیف `payments` (status=pending).
- `payment()->confirm()`: `payments.status=paid`، `orders.status=paid`، `invoices.status=paid`، ردیف جدید `transactions`.
- `refund()->process()`: ردیف `order_returns` + (اگر `toWallet()` باشد) ردیف `wallet_transactions` معادل اعتبار.

## قوانین

- Commerce به درگاه/SMS/میل/HTTP وصل نمی‌شود؛ فقط persistence و رویداد.
- اسنپ‌شات آیتم (عنوان، sku، منبع قیمت، ...) در `OrderItem.extra_attributes` ذخیره می‌شود؛ وابستگی به مدل‌های Shop نیست.
- این APIهای جدید additive هستند: `Commerce::config()`/`model()`/`newModel()` و مدل‌های lean موجود بدون تغییر باقی می‌مانند.
- ساخت مبلغ/تخفیف/موجودی پیچیده (pricing، campaign، evaluation) همچنان مسئولیت میزبان است — Commerce فقط عددهای نهایی را ذخیره می‌کند.
