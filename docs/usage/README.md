# طرز استفاده

تنظیمات و resolve مدل از فاساد؛ عملیات کسب‌وکار حالا دو مسیر دارد:

- **مدل‌محور (قدیمی)**: مدل‌های lean را مستقیم `create`/`update` کنید — [orders.md](orders.md)، [payments-invoices.md](payments-invoices.md)، [wallets-discounts.md](wallets-discounts.md).
- **Facade‌محور (جدید، additive)**: عملیات کانونیک سبد/سفارش/پرداخت/مرجوعی/کیف‌پول را با APIهای fluent روی `Commerce` انجام دهید — [quickstart.md](quickstart.md).

```php
use Karnoweb\Commerce\Facades\Commerce;

// مدل‌محور
Commerce::model('order');

// Facade‌محور (جدید)
Commerce::cart();     // CartBuilder
Commerce::checkout();  // CheckoutBuilder
Commerce::payment();  // PaymentBuilder
Commerce::refund();   // RefundBuilder
Commerce::wallet();   // WalletBuilder
```

| موضوع | سند |
|--------|------|
| نصب و تنظیمات | [getting-started.md](getting-started.md) |
| شروع سریع سرتاسری (Facade) | [quickstart.md](quickstart.md) |
| سفارش و سبد (مدل‌محور) | [orders.md](orders.md) |
| فاکتور و پرداخت (مدل‌محور) | [payments-invoices.md](payments-invoices.md) |
| کیف پول و تخفیف (مدل‌محور) | [wallets-discounts.md](wallets-discounts.md) |
| رویدادها | [events.md](events.md) |

مفاهیم دامنه: [../concepts/README.md](../concepts/README.md).

## کدام مسیر را انتخاب کنم؟

- برای جریان استاندارد «سبد → سفارش → فاکتور → پرداخت → مرجوعی» با idempotency و انتقال وضعیت درست، از Facade (`quickstart.md`) استفاده کنید.
- برای دسترسی خام به مدل‌ها (کوئری‌های سفارشی، گزارش، subclass میزبان)، همان الگوی مدل‌محور قبلی معتبر است — چیزی حذف نشده.
- هر دو مسیر روی همان جداول/مدل‌های lean کار می‌کنند؛ می‌توانید آن‌ها را ترکیب کنید (مثلاً سفارش را با Facade بسازید و بعد با مدل مستقیم گزارش بگیرید).
