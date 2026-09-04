<?php

declare(strict_types=1);

return [
    'enabled' => env('COMMERCE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Table prefix
    |--------------------------------------------------------------------------
    |
    | Every table this package creates/queries goes through
    | Karnoweb\Commerce\Support\CommerceTables::name(), which applies this
    | prefix (default `com_`) unless the table already starts with it.
    | Same pattern as karnoweb/laravel-inventory.
    |
    */
    'general' => [
        'prefix' => env('COMMERCE_TABLE_PREFIX', 'com_'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Table names (before prefixing)
    |--------------------------------------------------------------------------
    |
    | Override any logical key here to rename the underlying table without
    | touching migrations or models — CommerceTables::name('orders') always
    | resolves through this map first.
    |
    */
    'tables' => [
        'orders' => 'orders',
        'order_lines' => 'order_lines',
        'document_adjustments' => 'document_adjustments',
        'document_dimensions' => 'document_dimensions',
        'invoices' => 'invoices',
        'payments' => 'payments',
        'transactions' => 'transactions',
        'order_returns' => 'order_returns',
        'order_return_lines' => 'order_return_lines',
        'return_reasons' => 'return_reasons',
        'wallets' => 'wallets',
        'wallet_transactions' => 'wallet_transactions',
        'discounts' => 'discounts',
        'discount_user_group' => 'discount_user_group',
        'payment_methods' => 'payment_methods',
        'shipping_methods' => 'shipping_methods',
    ],

    /*
    |--------------------------------------------------------------------------
    | Host models (soft references only — never FK-constrained in package code)
    |--------------------------------------------------------------------------
    */
    'models' => [
        'user' => env('COMMERCE_USER_MODEL', 'App\\Models\\User'),
        'order' => env('COMMERCE_ORDER_MODEL', 'App\\Models\\Order'),
        'order_line' => env('COMMERCE_ORDER_LINE_MODEL', 'App\\Models\\OrderLine'),
        'document_adjustment' => env('COMMERCE_DOCUMENT_ADJUSTMENT_MODEL', 'App\\Models\\DocumentAdjustment'),
        'document_dimension' => env('COMMERCE_DOCUMENT_DIMENSION_MODEL', 'App\\Models\\DocumentDimension'),
        'order_return' => env('COMMERCE_ORDER_RETURN_MODEL', 'App\\Models\\OrderReturn'),
        'order_return_line' => env('COMMERCE_ORDER_RETURN_LINE_MODEL', 'App\\Models\\OrderReturnLine'),
        'return_reason' => env('COMMERCE_RETURN_REASON_MODEL', 'App\\Models\\ReturnReason'),
        'invoice' => env('COMMERCE_INVOICE_MODEL', 'App\\Models\\Invoice'),
        'payment' => env('COMMERCE_PAYMENT_MODEL', 'App\\Models\\Payment'),
        'transaction' => env('COMMERCE_TRANSACTION_MODEL', 'App\\Models\\Transaction'),
        'discount' => env('COMMERCE_DISCOUNT_MODEL', 'App\\Models\\Discount'),
        'wallet' => env('COMMERCE_WALLET_MODEL', 'App\\Models\\Wallet'),
        'wallet_transaction' => env('COMMERCE_WALLET_TRANSACTION_MODEL', 'App\\Models\\WalletTransaction'),
        'shipping_method' => env('COMMERCE_SHIPPING_METHOD_MODEL', 'App\\Models\\ShippingMethod'),
        'payment_method' => env('COMMERCE_PAYMENT_METHOD_MODEL', 'App\\Models\\PaymentMethod'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Key type strategy
    |--------------------------------------------------------------------------
    */
    'keys' => [
        'user_key_type' => env('COMMERCE_USER_KEY_TYPE', 'int'),
        'branch_key_type' => env('COMMERCE_BRANCH_KEY_TYPE', 'int'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Morph map aliases
    |--------------------------------------------------------------------------
    */
    'morph_map' => [
        'commerce_order' => env('COMMERCE_ORDER_MODEL', 'App\\Models\\Order'),
        'commerce_order_line' => env('COMMERCE_ORDER_LINE_MODEL', 'App\\Models\\OrderLine'),
        'commerce_invoice' => env('COMMERCE_INVOICE_MODEL', 'App\\Models\\Invoice'),
        'commerce_payment' => env('COMMERCE_PAYMENT_MODEL', 'App\\Models\\Payment'),
        'commerce_order_return' => env('COMMERCE_ORDER_RETURN_MODEL', 'App\\Models\\OrderReturn'),
        'commerce_wallet' => env('COMMERCE_WALLET_MODEL', 'App\\Models\\Wallet'),
    ],
];
