# کیف پول و تخفیف

## Wallet

کیف پول با morph `reference` به مالک (معمولاً User) و `branch_id`. **`branch_id` همیشه `NOT NULL` است** — قرارداد این پکیج: `0` یعنی «سراسری» (کیف پول بدون شعبهٔ خاص)، هرگز `null`. این کار ایندکس یکتای `(reference_type, reference_id, branch_id)` را روی همهٔ دیتابیس‌ها یکسان نگه می‌دارد (بعضی درایورها مثل MySQL چند `NULL` را در ایندکس یکتا متمایز می‌دانند، بعضی دیگر نه). تراکنش‌ها در `WalletTransaction` با `amount`، `sign` (بستانکار/بدهکار)، `type` و morph `transactionable`.

منطق موجودی و قفل همزمانی را در Action/سرویس میزبان پیاده کنید؛ مدل‌ها persistence هستند.

## Discount

کد تخفیف با نوع `percentage` یا `amount`، سقف استفاده، حداقل سفارش، بازهٔ اعتبار و فلگ فعال.

Pivot `discount_user_group` در مایگریشن هست؛ رابطهٔ Eloquent گروه‌ها معمولاً روی subclass میزبان اضافه می‌شود. **ارزیابی اینکه کد روی سبد اعمال شود** در سرویس میزبان است، نه داخل این پکیج.

## روش ارسال و پرداخت

`ShippingMethod` و `PaymentMethod` با SoftDeletes و `scopeActive` (`published`). عنوان/توضیح ارسال از `HasTranslation` پشتیبانی می‌کند.
