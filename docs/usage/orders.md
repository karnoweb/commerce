# سفارش و سبد

> برای جریان کانونیک با idempotency و انتقال وضعیت خودکار، از `Commerce::cart()`/`Commerce::checkout()` استفاده کنید — [quickstart.md](quickstart.md). این سند همان مسیر مدل‌محور قبلی را توضیح می‌دهد (هنوز معتبر، additive).

```php
use Karnoweb\Commerce\Enums\OrderStatusEnum;
use Karnoweb\Commerce\Enums\OrderTypeEnum;
use Karnoweb\Commerce\Facades\Commerce;

$Order = Commerce::model('order');
$OrderItem = Commerce::model('order_item');

// خط سبد (بدون order_id)
$OrderItem::query()->create([
    'user_id' => $userId,
    'product_id' => $productId,
    'quantity' => 2,
    'sale_price' => 500_000,
    'discount_amount' => 0,
]);

$cart = $OrderItem::query()->carts()->where('user_id', $userId)->get();

$order = $Order::query()->create([
    'order_number' => 'ORD-1001',
    'user_id' => $userId,
    'status' => OrderStatusEnum::PENDING,
    'type' => OrderTypeEnum::SALE,
    'subtotal' => 1_000_000,
    'discount_amount' => 0,
    'tax_amount' => 0,
    'shipping_amount' => 50_000,
    'total' => 1_050_000,
]);

// اتصال سبد به سفارش
$OrderItem::query()
    ->carts()
    ->where('user_id', $userId)
    ->update(['order_id' => $order->id]);
```

## قوانین

- ساخت سفارش «کامل» (قیمت‌گذاری، تخفیف، موجودی) را در Action میزبان نگه دارید؛ مدل فقط persistence است.
- برای `itemable` از مدل محصول configشده استفاده کنید.
- وضعیت را با معنای enum جلو ببرید؛ چرخهٔ مجاز (مثلاً pending→paid) قانون میزبان است.

## خطاها

پکیج استثناهای lifecycle مثل HR ندارد؛ اعتبارسنجی را در FormRequest/Action میزبان بنویسید.

## نتیجه ذخیره‌شده

ردیف `orders` و به‌روزرسانی `order_id` روی اقلام سبد. روابط `items`، `totals`، `invoices`، `payments` بعداً پر می‌شوند.
