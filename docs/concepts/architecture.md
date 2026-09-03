# معماری پکیج

پکیج یک **دامنه Commerce** است، نه ماژول فروش کامل. کنترلر، Livewire، Action، ارزیابی سبد و Policy داخل پکیج نیستند.

## لایه‌ها

| لایه | مسیر | نقش |
|------|------|------|
| Model | `src/Models/` | persistence و رابطه |
| Enum | `src/Enums/` | وضعیت/نوع سفارش و پرداخت، نوع تخفیف |
| Event | `src/Events/` | payload سبک (شناسه) برای مصرف خارجی |
| Support | `CommerceEventDispatcher`, morph map, resolve مدل |
| Facade | `Commerce` | config + model + macro |

قواعد:

1. میزبان مدل‌ها را subclass می‌کند و منطق حسابداری/CRM/لاگ را آنجا می‌گذارد.
2. لینک به محصول، آدرس، کمپین، deal فقط از config نرم است — بدون dependency سخت به shop/crm.
3. رویداد lean پکیج را با `CommerceEventDispatcher` بعد از commit بفرستید؛ رویدادهای غنی UI/نوتیف در میزبان بمانند.
4. سرویس‌هایی مثل `CartService` / `OrderPricingService` / `DiscountEvaluatorService` فعلاً در میزبان‌اند چون به سطح اپ وابسته‌اند.

## بیرون از scope

درگاه پرداخت واقعی، حرکت انبار، سند حسابداری، UI ادمین/فروشگاه، SMS.

**آینده:** استخراج تدریجی سرویس‌های قیمت/سبد پشت Contract مشترک با shop.
