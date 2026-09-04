# رویدادها

```php
use Karnoweb\Commerce\Events\OrderCreated;
use Karnoweb\Commerce\Events\OrderPaid;
use Karnoweb\Commerce\Events\InvoiceFullyPaid;
use Karnoweb\Commerce\Events\RefundCreated;
use Karnoweb\Commerce\Support\CommerceEventDispatcher;

CommerceEventDispatcher::dispatch(new OrderCreated(
    orderId: (int) $order->id,
    userId: $order->user_id,
));

CommerceEventDispatcher::dispatch(new OrderPaid(
    orderId: (int) $order->id,
    userId: $order->user_id,
));

CommerceEventDispatcher::dispatch(new InvoiceFullyPaid(
    invoiceId: (int) $invoice->id,
    orderId: $invoice->order_id,
));

CommerceEventDispatcher::dispatch(new RefundCreated(
    orderId: (int) $order->id,
    orderReturnId: (int) $orderReturn->id,
    amount: $orderReturn->amount,
    userId: $order->user_id,
));
```

اگر داخل تراکنش DB باشید، ارسال به بعد از commit موکول می‌شود.

## منتشرکننده‌های Facade

اگر از `Commerce::checkout()`, `Commerce::payment()` یا `Commerce::refund()` استفاده کنید ([quickstart.md](quickstart.md))، این رویدادها را خودِ سرویس‌های پکیج بعد از commit ارسال می‌کنند — نیازی به dispatch دستی نیست:

| متد Facade | رویداد |
|------------|--------|
| `checkout()->place()` | `OrderCreated` |
| `payment()->confirm()` | `OrderPaid` + `InvoiceFullyPaid` |
| `refund()->process()` | `RefundCreated` |

## الگوی دوگانه در میزبان

```php
// غنی برای UI / نوتیف / CRM
event(new \App\Events\Commerce\OrderCreated($order, $transaction, $context));

// lean برای مصرف‌کننده‌های پکیج‌محور
CommerceEventDispatcher::dispatch(new OrderCreated(
    orderId: (int) $order->id,
    userId: $order->user_id,
));
```

## Listener نمونه

```php
Event::listen(InvoiceFullyPaid::class, function (InvoiceFullyPaid $e): void {
    // فقط $e->invoiceId و $e->orderId
});
```

## قوانین

- payload رویداد پکیج را سنگین نکنید؛ Eloquent را در listener لود کنید.
- برای باطل‌سازی کش یا ساخت سند حسابداری به همین رویدادهای lean گوش دهید تا وابستگی معکوس به UI نداشته باشید.

## نتیجه ذخیره‌شده

خود dispatch چیزی در جدول commerce نمی‌نویسد؛ اثر در listenerهای شماست.
