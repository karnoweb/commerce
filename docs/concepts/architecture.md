# معماری پکیج

پکیج یک **دامنه Commerce** است، نه ماژول فروش کامل. کنترلر، Livewire، Action، ارزیابی سبد و Policy داخل پکیج نیستند.

## لایه‌ها

| لایه | مسیر | نقش |
|------|------|------|
| Model | `src/Models/` | persistence و رابطه |
| Enum | `src/Enums/` | وضعیت/نوع سفارش و پرداخت، نوع تخفیف |
| Event | `src/Events/` | payload سبک (شناسه) برای مصرف خارجی |
| Support | `CommerceEventDispatcher`, morph map, resolve مدل، `CommerceTables` |
| Facade | `Commerce` | config + model + macro |

قواعد:

1. میزبان مدل‌ها را subclass می‌کند و منطق حسابداری/CRM/لاگ را آنجا می‌گذارد.
2. لینک به محصول، آدرس، کمپین، deal فقط از config نرم است — بدون dependency سخت به shop/crm.
3. رویداد lean پکیج را با `CommerceEventDispatcher` بعد از commit بفرستید؛ رویدادهای غنی UI/نوتیف در میزبان بمانند.
4. سرویس‌هایی مثل `CartService` / `OrderPricingService` / `DiscountEvaluatorService` فعلاً در میزبان‌اند چون به سطح اپ وابسته‌اند.

## پیشوند و نام جدول‌ها (`CommerceTables`)

همهٔ جدول‌های پکیج (یک مایگریشن squash‌شدهٔ واحد: `database/migrations_squashed/2026_09_04_000000_create_commerce_schema.php`) با پیشوند قابل‌تنظیم `config('commerce.general.prefix', 'com_')` ساخته می‌شوند. `BaseModel::getTable()` و مایگریشن هر دو از `Karnoweb\Commerce\Support\CommerceTables::name($key)` عبور می‌کنند تا هم پیشوند و هم rename تک‌جدولی (`config('commerce.tables.<key>')`) در یک مسیر resolve شود — جزئیات و مثال در [getting-started.md](../usage/getting-started.md).

## Adjustments و Dimensions به‌جای ستون ثابت

`orders`/`invoices` هیچ ستون `discount_amount`/`tax_amount`/`shipping_amount` ندارند. این مقادیر در جدول polymorphic `document_adjustments` (`adjustable_type`/`adjustable_id`, `key`, `sign`, `amount`, `payload`) ذخیره می‌شوند — یک جدول، قابل استفاده روی `Order`، `Invoice` یا هر مدل دیگری که trait `HasAdjustments` را استفاده کند؛ accessorهای `shippingAmount()`/`taxAmount()`/`discountAmount()` روی آن جمع می‌زنند.

ابعاد گزارش‌گیری (`sales_unit_id`, `warehouse_id`, `channel_id`, ...) هم به همین شکل در جدول polymorphic `document_dimensions` (`documentable_type`/`documentable_id`, `key`, `value_int`/`value_string`/`value_json`) ذخیره می‌شوند — trait `HasDimensions`. مثال‌های کوئری فیلتر ترکیبی (OR روی مقدار، AND روی چند بُعد) در [quickstart.md](../usage/quickstart.md).

## بیرون از scope

درگاه پرداخت واقعی، حرکت انبار، سند حسابداری، UI ادمین/فروشگاه، SMS.

**آینده:** استخراج تدریجی سرویس‌های قیمت/سبد پشت Contract مشترک با shop.
