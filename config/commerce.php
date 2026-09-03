<?php

declare(strict_types=1);

return [
    'models' => [
        'user' => env('COMMERCE_USER_MODEL', 'App\\Models\\User'),
        'order' => env('COMMERCE_ORDER_MODEL', 'App\\Models\\Order'),
        'order_item' => env('COMMERCE_ORDER_ITEM_MODEL', 'App\\Models\\OrderItem'),
        'order_return' => env('COMMERCE_ORDER_RETURN_MODEL', 'App\\Models\\OrderReturn'),
        'order_total' => env('COMMERCE_ORDER_TOTAL_MODEL', 'App\\Models\\OrderTotal'),
        'invoice' => env('COMMERCE_INVOICE_MODEL', 'App\\Models\\Invoice'),
        'payment' => env('COMMERCE_PAYMENT_MODEL', 'App\\Models\\Payment'),
        'transaction' => env('COMMERCE_TRANSACTION_MODEL', 'App\\Models\\Transaction'),
        'discount' => env('COMMERCE_DISCOUNT_MODEL', 'App\\Models\\Discount'),
        'wallet' => env('COMMERCE_WALLET_MODEL', 'App\\Models\\Wallet'),
        'wallet_transaction' => env('COMMERCE_WALLET_TRANSACTION_MODEL', 'App\\Models\\WalletTransaction'),
        'shipping_method' => env('COMMERCE_SHIPPING_METHOD_MODEL', 'App\\Models\\ShippingMethod'),
        'payment_method' => env('COMMERCE_PAYMENT_METHOD_MODEL', 'App\\Models\\PaymentMethod'),
        'product' => env('COMMERCE_PRODUCT_MODEL', 'App\\Models\\Product'),
        'campaign' => env('COMMERCE_CAMPAIGN_MODEL', 'App\\Models\\Campaign'),
        'address' => env('COMMERCE_ADDRESS_MODEL', 'App\\Models\\Address'),
    ],
    'keys' => [
        'user_key_type' => env('COMMERCE_USER_KEY_TYPE', 'int'),
        'branch_key_type' => env('COMMERCE_BRANCH_KEY_TYPE', 'int'),
    ],
    'tables' => [
        'prefix' => env('COMMERCE_TABLE_PREFIX', ''),
    ],
    'morph_map' => [
        'commerce_order' => env('COMMERCE_ORDER_MODEL', 'App\\Models\\Order'),
        'commerce_invoice' => env('COMMERCE_INVOICE_MODEL', 'App\\Models\\Invoice'),
        'commerce_payment' => env('COMMERCE_PAYMENT_MODEL', 'App\\Models\\Payment'),
        'commerce_wallet' => env('COMMERCE_WALLET_MODEL', 'App\\Models\\Wallet'),
        'commerce_order_item' => env('COMMERCE_ORDER_ITEM_MODEL', 'App\\Models\\OrderItem'),
    ],
];
