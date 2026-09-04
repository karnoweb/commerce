# نصب و راه‌اندازی

```bash
composer require karnoweb/commerce:^13.0
php artisan vendor:publish --tag=commerce-config
php artisan vendor:publish --tag=commerce-migrations   # الزامی
php artisan vendor:publish --tag=commerce-lang         # اختیاری
php artisan vendor:publish --tag=commerce-seeders      # اختیاری — پیش‌فرض روش پرداخت/ارسال/دلیل مرجوعی
php artisan migrate
```

مایگریشن‌ها یک فایل واحد و squash‌شده هستند (`database/migrations_squashed/2026_09_04_000000_create_commerce_schema.php`) — هیچ FK سختی به جداول میزبان/shop ندارند؛ همه ارجاع‌ها (`user_id`, `branch_id`, `sales_unit_id`, `warehouse_id`, `item_id`, ...) کلید نرم (`unsignedBigInteger` + index) هستند، بدون پیش‌نیاز جدول میزبان.

## پیشوند و نام‌های جدول قابل‌تنظیم

**قبل از اولین `migrate`** پیشوند پیش‌فرض `com_` است — یعنی جدول واقعی `orders` در دیتابیس `com_orders` خواهد بود. برای تغییر، پیش از migrate در `.env` تنظیم کنید:

```env
COMMERCE_TABLE_PREFIX=com_
```

خالی‌کردن آن (`COMMERCE_TABLE_PREFIX=`) یعنی بدون پیشوند (جدول‌ها دقیقاً `orders`, `order_lines`, ...). برای تغییر نام یک جدول خاص (بدون تغییر پیشوند)، `config/commerce.php` را publish کنید و کلید مربوط را در بخش `tables` عوض کنید:

```php
// config/commerce.php
'tables' => [
    'orders' => 'sales_orders', // نتیجه: com_sales_orders
    // ...
],
```

هر جا در کد پکیج به یک جدول commerce نیاز باشد (مایگریشن، مدل)، از طریق `Karnoweb\Commerce\Support\CommerceTables::name('orders')` عبور می‌کند — یعنی تغییر این config بدون دست‌زدن به کد اثر می‌کند. مدل‌ها (`extends BaseModel`) خودشان `getTable()` را با همین منطق override می‌کنند؛ چیزی در سطح مدل تنظیم نمی‌کنید.

> تغییر پیشوند/نام جدول **بعد از** migrate یعنی باید rename دستی جدول‌ها یا rollback+migrate دوباره انجام دهید — این تنظیم را همان اول پروژه قفل کنید.

## Seederهای پیش‌فرض

```bash
php artisan db:seed --class="Karnoweb\Commerce\Database\Seeders\CommerceSeeder"
```

سه کاتالوگ کوچک را idempotent پر می‌کند (اجرای دوباره خطا نمی‌دهد، ردیف تکراری نمی‌سازد):

- `payment_methods`: `cash`, `card`, `online`, `wallet`
- `shipping_methods`: `standard`, `pickup`
- `return_reasons`: `damaged`, `wrong_item`, `not_needed`, `other`

```env
COMMERCE_TABLE_PREFIX=
COMMERCE_USER_KEY_TYPE=int
COMMERCE_BRANCH_KEY_TYPE=int
COMMERCE_ORDER_MODEL=App\Models\Order
COMMERCE_ORDER_LINE_MODEL=App\Models\OrderLine
# … سایر COMMERCE_*_MODEL
```

## فاساد

```php
use Karnoweb\Commerce\Facades\Commerce;

Commerce::config('general.prefix'); // 'com_' پیش‌فرض
Commerce::model('order');
Commerce::newModel('invoice');

Commerce::macro('pendingOrders', function (int $userId) {
    return Commerce::model('order')::query()
        ->where('user_id', $userId)
        ->where('financial_status', 'pending')
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
- پیشوند جدول را همان ابتدای پروژه تنظیم کنید — تغییر آن بعد از داده‌های واقعی نیاز به مهاجرت دستی دارد.

## خطاها

خطاهای نصب معمولاً ترتیب migrate و FK هستند.

## نتیجه ذخیره‌شده

جداول سفارش/پرداخت/کیف‌پول/تخفیف (همه با پیشوند `commerce.general.prefix`) و morph mapهای `commerce_*` در boot ثبت می‌شوند.
