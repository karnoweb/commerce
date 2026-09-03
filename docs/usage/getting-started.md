# نصب و راه‌اندازی

```bash
composer require karnoweb/commerce:^13.0
php artisan vendor:publish --tag=commerce-config
php artisan vendor:publish --tag=commerce-lang   # اختیاری
php artisan migrate
```

مایگریشن‌ها از پکیج لود می‌شوند. پیش‌نیاز جداول میزبان/shop مثل `users`، `products`، `addresses`، `branches` بسته به FKهای پروژه باید از قبل آماده باشند.

```env
COMMERCE_TABLE_PREFIX=
COMMERCE_USER_KEY_TYPE=int
COMMERCE_BRANCH_KEY_TYPE=int
COMMERCE_ORDER_MODEL=App\Models\Order
COMMERCE_PRODUCT_MODEL=App\Models\Product
# … سایر COMMERCE_*_MODEL
```

## فاساد

```php
use Karnoweb\Commerce\Facades\Commerce;

Commerce::config('tables.prefix');
Commerce::model('order');
Commerce::newModel('invoice');

Commerce::macro('pendingOrders', function (int $userId) {
    return Commerce::model('order')::query()
        ->where('user_id', $userId)
        ->where('status', 'pending')
        ->get();
});
```

## subclass پیشنهادی

```php
namespace App\Models;

class Order extends \Karnoweb\Commerce\Models\Order
{
    // Activity log، رابطه deal، متدهای دامنه میزبان
}
```

## قوانین

- `karnoweb/translation` برای ترجمهٔ روش ارسال لازم است.
- پکیج Policy و فیلتر شعبه ندارد؛ قبل از API عمومی در میزبان محافظت کنید.

## خطاها

خطاهای نصب معمولاً ترتیب migrate و FK هستند.

## نتیجه ذخیره‌شده

جداول سفارش/پرداخت/کیف‌پول/تخفیف و morph mapهای `commerce_*` در boot ثبت می‌شوند.
