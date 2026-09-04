# فاکتور و پرداخت

## Invoice

فاکتور برای هر سفارش **اجباری** است (`CheckoutService::finalize()` همیشه یک ردیف `invoices` می‌سازد) اما می‌تواند مستقل هم باشد (`order_id` nullable — `Commerce::invoices()->issueStandalone()`). وضعیت رشته‌ای در مدل lean است؛ چرخهٔ بیزنس کامل در میزبان/هماهنگ‌کننده billing تعریف می‌شود.

اتصال به سند حسابداری میزبان (مثل یک `document_id` نرم در `extra_attributes`) روی subclass میزبان تعریف می‌شود؛ پکیج ستون اختصاصی برای آن ندارد.

## Payment

ردیف تسویه نسبت به سفارش/فاکتور/روش پرداخت.

| Enum | نمونه‌ها |
|------|----------|
| `PaymentStatusEnum` | pending, paid, failed, refunded, cancelled |
| `PaymentTypeEnum` | online, cash, card_to_card, bank, wallet |

## Transaction

جزئیات درگاه (authority، ref، tracking، card، gateway_response) جدا از ردیف Payment نگه داشته می‌شود تا تاریخچهٔ درگاه با وضعیت تسویه قاطی نشود.

## رویدادها

| Event | معنا |
|-------|------|
| `OrderCreated` | سفارش ساخته شد |
| `OrderPaid` | سفارش پرداخت شد |
| `InvoiceFullyPaid` | فاکتور کامل تسویه شد |

همه فقط شناسه حمل می‌کنند تا listener میزبان مدل غنی را خودش لود کند.
