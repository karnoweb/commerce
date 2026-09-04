# فاکتور و پرداخت

> برای جریان کانونیک (initiate → confirm، Transaction، انتقال وضعیت Order/Invoice) از `Commerce::payments()` (یا alias قدیمی `payment()`) استفاده کنید — [quickstart.md](quickstart.md). این سند همان مسیر مدل‌محور قبلی را توضیح می‌دهد (هنوز معتبر، additive). توجه: `payments.invoice_id` الزامی است — هر پرداخت باید یک فاکتور را تسویه کند.

```php
use Karnoweb\Commerce\Enums\PaymentStatusEnum;
use Karnoweb\Commerce\Enums\PaymentTypeEnum;
use Karnoweb\Commerce\Facades\Commerce;

$Invoice = Commerce::model('invoice');
$Payment = Commerce::model('payment');

$invoice = $Invoice::query()->create([
    'invoice_number' => 'INV-1',
    'branch_id' => 1,
    'user_id' => $userId,
    'order_id' => $order->id, // یا null
    'amount' => 1_050_000,
    'invoice_date' => now()->toDateString(),
    'status' => 'draft',
]);

$Payment::query()->create([
    'user_id' => $userId,
    'order_id' => $order->id,
    'invoice_id' => $invoice->id,
    'payment_method_id' => $methodId,
    'amount' => 1_050_000,
    'type' => PaymentTypeEnum::ONLINE,
    'status' => PaymentStatusEnum::PENDING,
]);
```

روش‌های فعال:

```php
use Karnoweb\Commerce\Models\PaymentMethod;
use Karnoweb\Commerce\Models\ShippingMethod;

PaymentMethod::query()->active()->orderBy('ordering')->get();
ShippingMethod::query()->active()->orderBy('ordering')->get();
```

## قوانین

- پاسخ درگاه را در `Transaction` نگه دارید؛ وضعیت تسویه را روی `Payment` جلو ببرید.
- بعد از تسویه کامل فاکتور، هم رویداد غنی میزبان و هم `InvoiceFullyPaid` lean را در نظر بگیرید — [events.md](events.md).
- پل `document()` حسابداری روی subclass میزبان است.

## خطاها

اعتبار مبلغ/وضعیت تکراری را در میزبان enforce کنید.

## نتیجه ذخیره‌شده

ردیف‌های `invoices`، `payments` و در صورت نیاز `transactions`.
