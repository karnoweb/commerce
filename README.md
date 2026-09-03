# Karnoweb Commerce

پکیج دامنهٔ **بازرگانی** برای لاراول: سفارش، اقلام/جمع‌ها/مرجوعی، فاکتور، پرداخت، تراکنش درگاه، کیف پول، تخفیف، روش ارسال و پرداخت.

**مستندات:** [docs/README.md](docs/README.md) — [مفاهیم](docs/concepts/README.md) و [طرز استفاده](docs/usage/README.md)  
قرارداد معماری (انگلیسی): [COMMERCE_PACKAGE.md](COMMERCE_PACKAGE.md)

## Requirements

- PHP 8.3+
- Laravel 13.x
- `karnoweb/translation` ^13.0

## Installation

```bash
composer require karnoweb/commerce:^13.0
php artisan vendor:publish --tag=commerce-config
php artisan vendor:publish --tag=commerce-lang   # اختیاری
php artisan migrate
```

## قابلیت‌ها (v13.0)

مدل‌های lean + config/morph + رویداد. سرویس‌های پیچیدهٔ قیمت سبد و ارزیابی تخفیف در **میزبان** می‌مانند.

| حوزه | نقطه ورود |
|------|-----------|
| تنظیمات و مدل | فاساد `Commerce` |
| رویداد بعد از commit | `CommerceEventDispatcher` |
| persistence | مدل‌های `Order`, `Invoice`, `Payment`, `Wallet`, … |

**در پکیج نیست:** UI، Action/Pipeline، درگاه واقعی (`karnoweb/payment`)، کاتالوگ (`karnoweb/shop`)، سند حسابداری.

## مثال سریع

```php
use Karnoweb\Commerce\Facades\Commerce;
use Karnoweb\Commerce\Enums\OrderStatusEnum;
use Karnoweb\Commerce\Support\CommerceEventDispatcher;
use Karnoweb\Commerce\Events\OrderCreated;

$order = Commerce::model('order')::query()->create([
    'order_number' => 'ORD-1001',
    'user_id' => $userId,
    'status' => OrderStatusEnum::PENDING,
    'total' => 1_050_000,
]);

CommerceEventDispatcher::dispatch(new OrderCreated(
    orderId: (int) $order->id,
    userId: $order->user_id,
));
```

بیشتر: [docs/usage/README.md](docs/usage/README.md)

## License

MIT
