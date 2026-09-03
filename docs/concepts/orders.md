# سفارش و اقلام

```text
User
 └── Order
      ├── OrderItem (product / itemable morph)
      ├── OrderTotal
      ├── OrderReturn
      ├── Invoice ── Payment
      ├── Payment
      └── Transaction (درگاه)
```

## Order

وضعیت‌ها (`OrderStatusEnum`): `cart`, `pending`, `paid`, `processing`, `shipped`, `delivered`, `cancelled`, `refunded`, `expired`.

انواع (`OrderTypeEnum`): `sale`, `sale_return`, `purchase`, `purchase_return`.

فیلدهای نرم برای یکپارچگی: `deal_id` (CRM)، `campaign_id`، `branch_id`، `address_id`، مبلغ‌های مالیاتی/حسابداری.

## OrderItem

- می‌تواند بدون `order_id` باشد → سبد (`scopeCarts`).
- با ذخیره، `itemable_*` از `product_id` همگام می‌شود و `price` ↔ `sale_price` هم‌تراز می‌مانند.
- Accessorهایی مثل `total`، `total_price`، `final_unit_price` برای محاسبهٔ خط.

## OrderTotal / OrderReturn

تعدیل‌های سطری (نوع، علامت، مبلغ، payload) و مرجوعی با `document_id` نرم برای پل حسابداری در میزبان.
