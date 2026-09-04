# رویدادها

```php
use Karnoweb\Commerce\Events\OrderCreated;
use Karnoweb\Commerce\Events\InvoiceIssued;
use Karnoweb\Commerce\Events\PaymentInitiated;
use Karnoweb\Commerce\Events\PaymentConfirmed;
use Karnoweb\Commerce\Events\OrderPaid;
use Karnoweb\Commerce\Events\InvoiceFullyPaid;
use Karnoweb\Commerce\Events\RefundCreated;
use Karnoweb\Commerce\Events\ReturnCreated;
use Karnoweb\Commerce\Support\CommerceEventDispatcher;

CommerceEventDispatcher::dispatch(new OrderCreated(
    orderId: (int) $order->id,
    userId: $order->user_id,
));

CommerceEventDispatcher::dispatch(new InvoiceIssued(
    invoiceId: (int) $invoice->id,
    orderId: $invoice->order_id, // null برای فاکتور مستقل
    amount: $invoice->amount,
    userId: $invoice->user_id,
));

CommerceEventDispatcher::dispatch(new PaymentInitiated(
    paymentId: (int) $payment->id,
    invoiceId: (int) $payment->invoice_id,
    orderId: $payment->order_id, // nullable
    amount: $payment->amount,
    userId: $payment->user_id,
));

CommerceEventDispatcher::dispatch(new PaymentConfirmed(
    paymentId: (int) $payment->id,
    invoiceId: (int) $payment->invoice_id,
    orderId: $payment->order_id, // nullable
    amount: $payment->amount,
    userId: $payment->user_id,
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
    amount: $orderReturn->total_amount,
    userId: $order->user_id,
));

CommerceEventDispatcher::dispatch(new ReturnCreated(
    orderId: (int) $order->id,
    orderReturnId: (int) $orderReturn->id,
    totalAmount: $orderReturn->total_amount,
    lines: [
        ['orderLineId' => 1, 'quantity' => 1, 'amount' => 1_000_000],
    ],
    userId: $order->user_id,
));
```

اگر داخل تراکنش DB باشید، ارسال به بعد از commit موکول می‌شود.

## منتشرکننده‌های Facade

اگر از `Commerce::checkout()`, `Commerce::invoices()`, `Commerce::payments()`, `Commerce::refund()` یا `Commerce::returns()` استفاده کنید ([quickstart.md](quickstart.md))، این رویدادها را خودِ سرویس‌های پکیج بعد از commit ارسال می‌کنند — نیازی به dispatch دستی نیست:

| متد Facade | رویداد |
|------------|--------|
| `checkout()->finalize()` (یا alias `place()`) | `OrderCreated` (معادل مفهومی «OrderPlaced») + `InvoiceIssued` (فاکتور اجباری) |
| `invoices()->issueStandalone()` / `invoices()->create()` | `InvoiceIssued` |
| `payments()->initiate()` (یا alias `payment()`) | `PaymentInitiated` |
| `payments()->confirm()` | `PaymentConfirmed` (همیشه) + `OrderPaid` (فقط اگر order-bound) + `InvoiceFullyPaid` |
| `refund()->process()` | `RefundCreated` (فقط مبلغ؛ legacy) |
| `returns()->finalizeRefund*()` | `ReturnCreated` (شامل `lines: [{orderLineId, quantity, amount}]`) |

## الگوی دوگانه در میزبان

```php
// غنی برای UI / نوتیف / CRM
event(new \App\Events\Commerce\OrderCreated($order, $invoice, $context));

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
