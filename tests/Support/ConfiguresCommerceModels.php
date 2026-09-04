<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Tests\Support;

use Karnoweb\Commerce\Models\Discount;
use Karnoweb\Commerce\Models\DocumentAdjustment;
use Karnoweb\Commerce\Models\DocumentDimension;
use Karnoweb\Commerce\Models\Invoice;
use Karnoweb\Commerce\Models\Order;
use Karnoweb\Commerce\Models\OrderLine;
use Karnoweb\Commerce\Models\OrderReturn;
use Karnoweb\Commerce\Models\OrderReturnLine;
use Karnoweb\Commerce\Models\Payment;
use Karnoweb\Commerce\Models\PaymentMethod;
use Karnoweb\Commerce\Models\ReturnReason;
use Karnoweb\Commerce\Models\ShippingMethod;
use Karnoweb\Commerce\Models\Transaction;
use Karnoweb\Commerce\Models\Wallet;
use Karnoweb\Commerce\Models\WalletTransaction;
use Karnoweb\Commerce\Tests\Fixtures\FakeUser;

/**
 * Wires config('commerce.models.*') to this package's own models for a
 * standalone test run — no host App\Models\* required.
 */
trait ConfiguresCommerceModels
{
    protected function configureCommerceModels(): void
    {
        config([
            'commerce.models.user' => FakeUser::class,
            'commerce.models.order' => Order::class,
            'commerce.models.order_line' => OrderLine::class,
            'commerce.models.document_adjustment' => DocumentAdjustment::class,
            'commerce.models.document_dimension' => DocumentDimension::class,
            'commerce.models.order_return' => OrderReturn::class,
            'commerce.models.order_return_line' => OrderReturnLine::class,
            'commerce.models.return_reason' => ReturnReason::class,
            'commerce.models.invoice' => Invoice::class,
            'commerce.models.payment' => Payment::class,
            'commerce.models.transaction' => Transaction::class,
            'commerce.models.discount' => Discount::class,
            'commerce.models.wallet' => Wallet::class,
            'commerce.models.wallet_transaction' => WalletTransaction::class,
            'commerce.models.shipping_method' => ShippingMethod::class,
            'commerce.models.payment_method' => PaymentMethod::class,
        ]);
    }
}
