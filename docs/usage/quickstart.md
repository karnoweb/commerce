# شروع سریع — Facade سرتاسری (schema ژنریک v3)

این سند مسیر کامل «سبد → سفارش → فاکتور (اجباری) → پرداخت → مرجوعی» را فقط با فاساد `Commerce` نشان می‌دهد؛ بدون `Order::create` دستی، بدون `update(['status' => ...])` پخش‌شده در کد میزبان. پکیج فقط persistence و رویدادهای lean را انجام می‌دهد — **به درگاه/SMS/میل وصل نمی‌شود**؛ نتیجهٔ نهایی درگاه را میزبان به `Commerce::payments()->confirm()` گزارش می‌کند.

> **تغییر بنیادی نسبت به نسخه‌های قبل:** دیگر هیچ `product_id`ای در schema یا کد وجود ندارد. هر خط سفارش یک ارجاع ژنریک `item_type` + `item_id` (soft، nullable) + `item_name` (اسنپ‌شات الزامی) است. همچنین `orders`/`invoices` دیگر ستون ثابت `discount_amount`/`tax_amount`/`shipping_amount` ندارند — این‌ها فقط در جدول polymorphic `document_adjustments` ثبت می‌شوند؛ ابعاد گزارش‌گیری (`sales_unit_id`, `warehouse_id`, ...) در جدول polymorphic `document_dimensions`. همهٔ جدول‌ها با پیشوند قابل‌تنظیم (پیش‌فرض `com_`) ساخته می‌شوند — [getting-started.md](getting-started.md).

```php
use Karnoweb\Commerce\Facades\Commerce;
use Karnoweb\Commerce\Enums\PaymentTypeEnum;

$userId = 9001;
$branchId = 3;
$salesUnitId = 77;   // اختیاری — کدام واحد فروش این سفارش را ثبت کرده
$warehouseId = 12;   // اختیاری — کدام انبار درگیر است

// 1) افزودن به سبد — OrderLine با order_id = null.
//    itemType یک رشتهٔ آزاد است ('shop.product', 'custom.text', ...)؛
//    itemId ارجاع soft (بدون FK)؛ itemName اسنپ‌شات الزامی.
//    lineTotal = quantity x unitPrice — بدون ستون تخفیف/مالیات در سطح خط.
Commerce::cart()
    ->forUser($userId)
    ->branchId($branchId)
    ->addLine(
        itemType: 'shop.product',
        name: 'Coffee Beans 1kg',
        quantity: 2,
        unitPrice: 1_000_000,
        itemId: 501,        // ارجاع soft به کاتالوگ (بدون FK)
        sku: 'COF-1KG',
        uomCode: 'kg',
    );

// 2) ثبت سفارش از روی سبد — Order می‌سازد، خطوط سبد را متصل می‌کند،
//    shipping/tax/discount + هر adjustment دلخواه را در document_adjustments
//    ثبت می‌کند (polymorphic روی order)، sales_unit_id/warehouse_id را هم
//    روی order و هم به‌عنوان ردیف document_dimensions می‌نویسد، فاکتور
//    اجباریِ سفارش را می‌سازد، و OrderCreated + InvoiceIssued را بعد از
//    commit ارسال می‌کند.
$result = Commerce::checkout()
    ->forUser($userId)
    ->branchId($branchId)
    ->salesUnitId($salesUnitId)
    ->warehouseId($warehouseId)
    ->shippingAmount(50_000)
    ->idempotencyKey('checkout:user:9001:cart:active')
    ->finalize();

$order = $result->order;     // Order
$invoice = $result->invoice; // Invoice — همیشه ساخته می‌شود، هرگز null نیست

$order->shippingAmount(); // 50000 — accessor محاسبه‌شده از document_adjustments، نه ستون DB

// 3) شروع پرداخت — Payment(PENDING) می‌سازد. پرداخت همیشه به یک فاکتور
//    وصل است (invoice_id الزامی)؛ order فقط یک لینک denormalized اختیاری است.
$payment = Commerce::payments()
    ->forInvoice($invoice)
    ->forOrder($order)
    ->methodId(1)
    ->type(PaymentTypeEnum::ONLINE)
    ->amount((int) $invoice->amount)
    ->idempotencyKey('pay:invoice:'.$invoice->id.':attempt:1')
    ->initiate();

// 4) تأیید نتیجهٔ درگاه که میزبان گرفته است.
//    Payment(PAID)، Order(PAID)، Invoice(paid)، Transaction (payment_id-محور) می‌سازد،
//    و PaymentConfirmed + OrderPaid + InvoiceFullyPaid را بعد از commit ارسال می‌کند.
Commerce::payments()->confirm(
    payment: $payment,
    gateway: 'zarinpal',           // فقط رشته
    refId: 'REF-123',
    trackingCode: 'TRK-777',
    paidAt: now(),
    gatewayPayload: ['raw' => '...'],
);

// 5) مرجوعی بر اساس تعداد — دقیقاً کدام خط و چند واحد برمی‌گردد، با دلیل
//    نرمال‌شده (return_reason_id — [seed شده](getting-started.md) یا هر id
//    دلخواه شما در ReturnReason).
//    نمی‌تواند از (تعداد فروخته‌شده − قبلاً مرجوع‌شده) آن خط بیشتر شود.
$firstLine = $order->lines()->first();

$return = Commerce::returns()
    ->forOrder($order)
    ->idempotencyKey('return:order:'.$order->id.':v1')
    ->addLine(orderLineId: $firstLine->id, quantity: 1, reasonNote: 'Customer return')
    ->finalizeRefundToWallet(userId: $userId, branchId: $branchId);
```

## بی‌درنگی (idempotency)

`finalize()`/`place()`، `initiate()`/`confirm()` و `finalizeRefund*()` همگی `idempotencyKey(string $key)` اختیاری می‌پذیرند:

- کلید تکراری با همان payload → همان رکورد قبلی برگردانده می‌شود (retry امن). برای `checkout()->finalize()` یعنی همان `CheckoutResult { order, invoice }`.
- کلید تکراری با payload متفاوت (مثلاً user/order/amount دیگر) → `Karnoweb\Commerce\Exceptions\IdempotencyConflict` پرتاب می‌شود.
- `confirm()` کلید idempotency خودش را نمی‌گیرد؛ اما فراخوانی دوباره با همان `trackingCode` روی پرداخت PAID، بدون ساخت `Transaction` تکراری، همان `Payment` را برمی‌گرداند. `trackingCode` متفاوت روی پرداخت PAID، `CannotConfirmAlreadyPaidPayment` پرتاب می‌کند.

ستون‌های `idempotency_key` روی `orders`، `invoices`، `payments`، `order_returns` و `wallet_transactions` نال‌پذیر و یکتا هستند.

## خطاهای دامنه

| استثنا | چه زمانی |
|--------|----------|
| `CannotCheckoutEmptyCart` | `checkout()->finalize()` وقتی سبد کاربر خالی است |
| `CannotPayCancelledOrder` | `payments()->initiate()` روی سفارش لغوشده |
| `CannotConfirmAlreadyPaidPayment` | `payments()->confirm()` دوباره روی پرداخت PAID با `trackingCode` متفاوت |
| `RefundAmountExceedsPaidAmount` | `refund()->process()` وقتی مبلغ درخواستی + مرجوعی‌های قبلی از کل سفارش بیشتر شود |
| `CannotReturnWithoutLines` | `returns()->finalizeRefund*()` بدون هیچ `addLine()` |
| `ReturnLineNotFoundInOrder` | `orderLineId` متعلق به سفارش هدف نیست |
| `ReturnQuantityExceedsAvailable` | تعداد مرجوعی درخواستی + قبلاً مرجوع‌شده از تعداد فروخته‌شدهٔ آن خط بیشتر است |
| `IdempotencyConflict` | کلید idempotency تکراری با payload متفاوت |

همهٔ این‌ها در `Karnoweb\Commerce\Exceptions\*` هستند و از `RuntimeException` ارث می‌برند.

## انواع خط (`Commerce::cart()`)

متد ژنریک و پیشنهادی `addLine()` است؛ چهار متد قدیمی به‌صورت alias نازک روی آن باقی مانده‌اند (deprecated، اما بدون تغییر کار می‌کنند):

| متد | `itemType` معادل | کاربرد |
|-----|-------------------|--------|
| `addLine(itemType: '...', ...)` | هر رشتهٔ دلخواه | خط عمومی — پیشنهادی |
| `addProductItem()` *(deprecated)* | `shop.product` | خط کاتالوگ معمولی |
| `addServiceItem()` *(deprecated)* | `custom.service` | خدمات/نصب/کارمزد بدون کاتالوگ |
| `addTextItem()` *(deprecated)* | `custom.text` | هزینه/کارمزد آزاد — `quantity=1`, `unitPrice=amount` |
| `addCustomItem()` *(deprecated)* | پارامتر `itemType` خودش | خط پلی‌مورفیک با `itemId` اختیاری |

هر خط می‌تواند `sku`، `uomCode` (واحد شمارش، مثل `kg`/`ea`)، `expiresAt` (تاریخ انقضا — مخصوص خرید/دریافت انبار) و `extra` (اسنپ‌شات دلخواه) داشته باشد. `line_total_amount` همیشه `quantity x unitPrice` است — هیچ پارامتر تخفیف/مالیات در سطح خط وجود ندارد؛ آن‌ها فقط در سطح سفارش، از طریق adjustments، ثبت می‌شوند. `branchId()` روی `CartBuilder` به هر خط بعدی اعمال می‌شود؛ `salesUnitId()`/`warehouseId()`/`addDimension()` هم به هر خط بعدی اعمال می‌شوند، اما به‌جای ستون، یک ردیف `document_dimensions` روی همان خط می‌سازند (پایین).

## Adjustments (`document_adjustments`) — به‌جای ستون‌های ثابت

`shippingAmount()`/`taxAmount()`/`discountAmount()` روی `checkout()` shortcut‌هایی روی جدول polymorphic `document_adjustments` هستند (`key`+`sign`+`amount`؛ `adjustable_type`/`adjustable_id` می‌تواند `Order` یا `Invoice` باشد). این سه شورتکات **همیشه** یک ردیف می‌سازند — حتی مقدار ۰ — تا گزارش‌گیری یکدست بماند. برای هر کلید دلخواه دیگر (کارمزد، رگرسیون، کد تخفیف، ...):

```php
Commerce::checkout()
    ->forUser($userId)
    ->addAdjustment('rounding', 1, sign: 1)
    ->addAdjustment('coupon', 5_000, sign: -1, payload: ['code' => 'WELCOME5'])
    ->finalize();
```

`total_amount` سفارش = `subtotal_amount` (مجموع `line_total_amount` خطوط) + Σ(`sign` × `amount`) روی همهٔ adjustmentها. برای خواندن شکست مبلغ بعداً، از accessorها استفاده کنید نه ستون خام:

```php
$order->shippingAmount(); // int — Σ(sign × amount) روی key='shipping'
$order->taxAmount();
$order->discountAmount(); // همیشه مثبت برگردانده می‌شود
$order->adjustments;      // Collection<DocumentAdjustment> کامل
```

`Commerce::invoices()` هم همین shortcutها (`taxAmount()`, `discountAmount()`, `addAdjustment()`) را دارد — روی فاکتور مستقل صرفاً برای **شکست گزارشی** ثبت می‌شوند و روی `amount()` (که همیشه شما صریح می‌دهید) اثر نمی‌گذارند.

## Dimensions (`document_dimensions`) — ابعاد گزارش‌گیری ژنریک

علاوه بر ستون‌های اختصاصی `sales_unit_id`/`warehouse_id` روی `orders`/`invoices` (فیلتر سریع بدون join)، هر بُعد دلخواه دیگر (`region_id`, `channel_id`, `cashier_id`, ...) با `addDimension()` به‌عنوان یک ردیف polymorphic در `document_dimensions` ذخیره می‌شود — روی `Order`، `Invoice`، حتی `OrderLine` (از طریق `CartBuilder`):

```php
Commerce::checkout()
    ->forUser($userId)
    ->salesUnitId(77)   // می‌نویسد: orders.sales_unit_id = 77 و یک ردیف document_dimensions
    ->warehouseId(12)   // همان الگو برای warehouse_id
    ->addDimension('channel_id', 5)
    ->addDimension('cashier_id', 21)
    ->finalize();
```

چون `document_dimensions` یک جدول polymorphic ساده است، فیلتر ترکیبی روی چند بُعد/چند مقدار بدون تغییر schema ممکن است:

```php
use Karnoweb\Commerce\Models\DocumentDimension;
use Karnoweb\Commerce\Models\Order;

// همهٔ سفارش‌هایی که واحد فروش‌شان 1 یا 2 است (OR روی مقدار)
$orderIds = DocumentDimension::query()
    ->where('documentable_type', (new Order)->getMorphClass())
    ->forKey('sales_unit_id')
    ->valueIn([1, 2])
    ->pluck('documentable_id');

// سفارش‌هایی که هم به کانال 5 وصل‌اند و هم انبار 12 (AND — دو join روی یک جدول)
$orderIds = Order::query()
    ->whereHas('dimensions', fn ($q) => $q->forKey('channel_id')->where('value_int', 5))
    ->whereHas('dimensions', fn ($q) => $q->forKey('warehouse_id')->where('value_int', 12))
    ->pluck('id');
```

## فاکتور مستقل (بدون سفارش)

`checkout()->finalize()` همیشه یک فاکتور اجباری برای سفارش می‌سازد. برای صورتحساب بدون هیچ سفارشی:

```php
$invoice = Commerce::invoices()->issueStandalone(
    amount: 500_000,
    userId: $userId,
    branchId: $branchId,
);
// $invoice->order_id === null
```

برای فاکتور *اضافه* روی یک سفارش موجود (نادر — `finalize()` معمولاً کافی است):

```php
Commerce::invoices()->forOrder($order)->create(invoiceNumber: 'INV-EXTRA-1');
```

## مرجوعی مقداری (`Commerce::returns()`) — به‌جای `refund()`

`Commerce::refund()` (مبتنی بر مبلغ، بدون ارجاع به خط) هنوز کار می‌کند، اما برای مرجوعی‌هایی که *دقیقاً* معلوم است کدام خط و چه تعدادی برمی‌گردد، از `returns()` استفاده کنید:

- `addLine(orderLineId, quantity, returnReasonId?, reasonNote?)` صف می‌شود؛ تا `finalizeRefund*()` چیزی persist نمی‌شود. (نام قدیمی `addItem()` هنوز به‌عنوان alias کار می‌کند.) `returnReasonId` یک ارجاع soft به `ReturnReason` است — کدهای پیش‌فرض seed‌شده (`damaged`, `wrong_item`, `not_needed`, `other`) یا هر ردیف دلخواه شما در همان جدول — [getting-started.md](getting-started.md).
- اعتبارسنجی: `مرجوع‌شده قبلی + تعداد جدید` نمی‌تواند از `quantity` همان `OrderLine` بیشتر شود؛ در غیر این صورت `ReturnQuantityExceedsAvailable` پرتاب می‌شود.
- `finalizeRefundToWallet(userId, branchId = 0)`: `OrderReturn` + سطرهای `OrderReturnLine` می‌سازد و مبلغ محاسبه‌شده را به کیف پول اعتبار می‌دهد. `branchId` اختیاری است — پیش‌فرض `0` («سراسری»، مطابق قرارداد کیف پول). (نام قدیمی `finalizeAndRefundToWallet()` هنوز alias است.)
- `finalizeRefund(amountOverride: ?int = null)`: مثل بالا اما بدون اعتبار کیف پول (بازپرداخت واقعی مسئولیت میزبان است). (نام قدیمی `finalizeAndRefund()` هنوز alias است.)
- هر دو، مثل `refund()->process()`، وقتی مجموع `order_returns.total_amount` سفارش به کل آن برسد، سفارش و پرداخت‌های PAID را به `REFUNDED` می‌برند (هر دو سرویس روی همان ستون می‌نویسند).
- رویداد `ReturnCreated` (شامل `orderId`, `orderReturnId`, `totalAmount`, `lines: [{orderLineId, quantity, amount}]`) بعد از commit ارسال می‌شود.

## نتیجهٔ ذخیره‌شده

- `cart()->addLine()`: یک ردیف `order_lines` با `order_id = null`، `item_type`/`item_id`/`item_name` پر شده، بدون هیچ `product_id`.
- `checkout()->finalize()`: ردیف `orders` (status=pending) + `order_id` روی خطوط سبد + ردیف‌های `document_adjustments` (polymorphic روی order) + (اگر dimension باشد) ردیف‌های `document_dimensions` + ردیف اجباری `invoices` (status=issued).
- `invoices()->issueStandalone()`: ردیف `invoices` با `order_id = null` (+ اختیاری `document_adjustments`/`document_dimensions` گزارشی).
- `payments()->initiate()`: ردیف `payments` (status=pending, `invoice_id` الزامی).
- `payments()->confirm()`: `payments.status=paid`، `orders.status=paid` (اگر order-bound)، `invoices.status=paid`، ردیف جدید `transactions` (کلیدشده با `payment_id`).
- `refund()->process()`: ردیف `order_returns` (بدون خط) + (اگر `toWallet()`) ردیف `wallet_transactions`.
- `returns()->finalizeRefund*()`: یک ردیف `order_returns` + یک ردیف `order_return_lines` به ازای هر `addLine()` (با `return_reason_id`/`reason_note`)، هرکدام با `unit_price_amount`/`amount` منجمد در لحظه مرجوعی.

## قوانین

- Commerce به درگاه/SMS/میل/HTTP وصل نمی‌شود؛ فقط persistence و رویداد.
- هیچ `product_id`ای در schema وجود ندارد — هر ارجاع به کاتالوگ/خدمت/ماژول از طریق `item_type`+`item_id` (soft) و `item_name` (اسنپ‌شات) است.
- هیچ `discount_amount`/`tax_amount`/`shipping_amount` ستونی روی `orders`/`invoices` وجود ندارد — `document_adjustments` منبع واحد حقیقت است.
- `sales_unit_id`/`warehouse_id`/`branch_id`/`user_id` همه کلیدهای soft هستند (بدون FK به جدول‌های میزبان).
- ساخت مبلغ/تخفیف/موجودی پیچیده (pricing، campaign، evaluation) مسئولیت میزبان است — Commerce فقط عددهای نهایی را به‌عنوان adjustment/line ذخیره می‌کند.
