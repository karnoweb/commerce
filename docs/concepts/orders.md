# سفارش و خطوط

```text
User
 └── Order (subtotal_amount, total_amount — بدون discount/tax/shipping ستون)
      ├── OrderLine (item_type + item_id soft + item_name snapshot — no product_id)
      ├── DocumentAdjustment (polymorphic؛ shipping/tax/discount/custom, +/- ledger)
      ├── DocumentDimension (polymorphic؛ sales_unit_id/warehouse_id/... — گزارش‌گیری ژنریک)
      ├── OrderReturn ── OrderReturnLine (quantity-based, tied to an OrderLine, return_reason_id normalized)
      └── Invoice (mandatory, order_id nullable for standalone invoices)
           └── Payment ── Transaction (گیت‌وی)
```

## Order

وضعیت‌ها (`OrderStatusEnum`): `cart`, `pending`, `paid`, `processing`, `shipped`, `delivered`, `cancelled`, `refunded`, `expired`.

انواع (`OrderTypeEnum`): `sale`, `sale_return`, `purchase`, `purchase_return`.

فیلدهای نرم برای یکپارچگی: `user_id`، `branch_id`، `sales_unit_id`، `warehouse_id` — همه بدون FK به جدول‌های میزبان. **تنها** ستون‌های مبلغ روی `orders`، `subtotal_amount` و `total_amount` هستند (هر دو `bigint`، کوچک‌ترین واحد پول) — هیچ `discount_amount`/`tax_amount`/`shipping_amount` ستونی وجود ندارد؛ آن‌ها منحصراً در `document_adjustments` هستند (پایین). متدهای `shippingAmount()`/`taxAmount()`/`discountAmount()` روی مدل `Order`/`Invoice` **accessor محاسبه‌شده** هستند، نه ستون DB.

## OrderLine

- می‌تواند بدون `order_id` باشد → سبد (`scopeCarts`).
- **هیچ `product_id`ای ندارد.** ارجاع همیشه `item_type` (رشتهٔ آزاد مثل `shop.product`/`custom.text`) + `item_id` (soft، nullable) + `item_name` (اسنپ‌شات الزامی) + `item_sku` (اختیاری) است.
- `quantity` یک `decimal(18,6)` است — پشتیبانی از مقادیر کسری (کیلوگرم، گرم، ...).
- `uom_code` (واحد شمارش) و `expires_at` (تاریخ انقضا، مخصوص خرید/دریافت انبار) اختیاری‌اند.
- `line_total_amount` همیشه `quantity x unit_price_amount` است — **هیچ ستون تخفیف/مالیات در سطح خط وجود ندارد**؛ هر شکست مبلغ فقط در سطح سفارش، از طریق `document_adjustments`، ثبت می‌شود.

## DocumentAdjustment — به‌جای ستون‌های ثابت

جدول `document_adjustments` یک لجر آزاد +/- است (`key`, `sign`, `amount`, `payload`) با رابطهٔ **polymorphic** (`adjustable_type`/`adjustable_id`) — یعنی روی `Order` *و* `Invoice` (و هر مدلی که تریت `HasAdjustments` را استفاده کند) قابل استفاده است. shortcut‌های `shippingAmount()`/`taxAmount()`/`discountAmount()` و هر کلید دلخواه دیگر روی همین جدول می‌نویسند؛ `total_amount` سفارش = `subtotal_amount` + Σ(`sign` × `amount`) روی همهٔ adjustmentها.

## DocumentDimension — ابعاد گزارش‌گیری ژنریک

جدول `document_dimensions` یک بُعد گزارش‌گیری ژنریک است (`key`, `value_int`/`value_string`/`value_json`) با رابطهٔ **polymorphic** (`documentable_type`/`documentable_id`) روی هر سندی که این پکیج مالک آن است (سفارش، فاکتور، خط سفارش، پرداخت، ...). `sales_unit_id`/`warehouse_id` هم ستون اختصاصی روی `orders`/`invoices` دارند (فیلتر سریع بدون join) و هم به‌صورت یک ردیف `document_dimensions` نوشته می‌شوند (فیلتر عمومی/ترکیبی — نمونه‌های کوئری در [quickstart.md](../usage/quickstart.md)).

## OrderReturn / OrderReturnLine

مرجوعی مبتنی بر تعداد است: هر `OrderReturnLine` به یک `OrderLine` مشخص وصل است و نمی‌تواند از (فروخته‌شده − قبلاً مرجوع‌شده) آن خط بیشتر شود. دلیل مرجوعی نرمال‌شده است: `return_reason_id` یک FK داخلی به `ReturnReason` (کدهای seed‌شده: `damaged`, `wrong_item`, `not_needed`, `other`) است؛ `reason_note` یک یادداشت آزاد در کنار آن.
