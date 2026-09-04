# کیف پول و تخفیف

> برای اعتبار/برداشت کانونیک با idempotency از `Commerce::wallet()` استفاده کنید — [quickstart.md](quickstart.md). این سند همان مسیر مدل‌محور قبلی را توضیح می‌دهد (هنوز معتبر، additive).

## کیف پول

```php
use Karnoweb\Commerce\Facades\Commerce;

$Wallet = Commerce::model('wallet');
$WalletTx = Commerce::model('wallet_transaction');

$wallet = $Wallet::query()->create([
    'reference_type' => $user::class,
    'reference_id' => $user->id,
    'branch_id' => 1, // همیشه NOT NULL — قرارداد: 0 = «سراسری» (بدون شعبه خاص)
    'primary' => true,
]);

$WalletTx::query()->create([
    'wallet_id' => $wallet->id,
    'amount' => 100_000,
    'sign' => 1, // بستانکار
    'type' => 'deposit',
    'published' => true,
]);
```

تغییر موجودی را داخل تراکنش DB و با قفل مناسب در Action میزبان انجام دهید.

## تخفیف

```php
use Karnoweb\Commerce\Enums\DiscountTypeEnum;

Commerce::model('discount')::query()->create([
    'code' => 'SUMMER10',
    'type' => DiscountTypeEnum::PERCENTAGE,
    'value' => 10,
    'min_order_amount' => 500_000,
    'max_discount_amount' => 200_000,
    'usage_limit' => 100,
    'usage_per_user' => 1,
    'is_active' => true,
    'starts_at' => now(),
    'expires_at' => now()->addMonth(),
]);
```

## قوانین

- اعمال کد روی سبد = سرویس میزبان (`DiscountEvaluatorService` و مشابه).
- محدودیت گروه کاربری از pivot `discount_user_group`؛ رابطه را در subclass اضافه کنید.
- پرداخت از نوع `wallet` فقط نوع را مشخص می‌کند؛ کسر از کیف پول جداگانه است.

## خطاها

پکیج از دوبار خرج‌کردن موجودی جلوگیری نمی‌کند مگر شما در میزبان قفل بگذارید.

## نتیجه ذخیره‌شده

ردیف‌های `wallets` / `wallet_transactions` / `discounts` (و در صورت attach، pivot گروه کاربری).
