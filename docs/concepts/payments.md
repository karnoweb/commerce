# فاکتور و پرداخت

## Invoice

فاکتور می‌تواند به سفارش وصل باشد یا مستقل (`order_id` nullable). وضعیت رشته‌ای در مدل lean است؛ چرخهٔ بیزنس کامل در میزبان/هماهنگ‌کننده billing تعریف می‌شود.

`document_id` نرم برای اتصال به سند حسابداری — رابطهٔ Eloquent معمولاً روی subclass میزبان است.

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
