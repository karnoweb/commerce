<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Facades;

use Illuminate\Support\Facades\Facade;
use Karnoweb\Commerce\Builders\CartBuilder;
use Karnoweb\Commerce\Builders\CheckoutBuilder;
use Karnoweb\Commerce\Builders\InvoiceBuilder;
use Karnoweb\Commerce\Builders\PaymentBuilder;
use Karnoweb\Commerce\Builders\RefundBuilder;
use Karnoweb\Commerce\Builders\ReturnBuilder;
use Karnoweb\Commerce\Builders\WalletBuilder;

/**
 * @method static mixed config(?string $key = null, mixed $default = null)
 * @method static class-string<\Illuminate\Database\Eloquent\Model> model(string $key)
 * @method static \Illuminate\Database\Eloquent\Model newModel(string $key)
 * @method static CartBuilder cart() Start building a user's cart — generic itemType/itemId/itemName lines (fluent API).
 * @method static CheckoutBuilder checkout() Start placing an order from a cart; finalize() always creates its mandatory invoice (fluent API).
 * @method static InvoiceBuilder invoices() Attach an additional invoice to an order, or issue a standalone invoice (order_id null) (fluent API).
 * @method static PaymentBuilder payment() Start initiating a payment against an invoice, or confirm a gateway outcome (fluent API).
 * @method static PaymentBuilder payments() Alias for payment() (fluent API).
 * @method static RefundBuilder refund() Start processing an amount-only refund (legacy; prefer returns()) (fluent API).
 * @method static ReturnBuilder returns() Start a quantity-based return tied to original sale lines (fluent API).
 * @method static WalletBuilder wallet() Start a wallet credit/debit (fluent API).
 *
 * @see \Karnoweb\Commerce\Commerce
 */
class Commerce extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'commerce';
    }
}
