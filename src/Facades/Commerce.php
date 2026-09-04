<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Facades;

use Illuminate\Support\Facades\Facade;
use Karnoweb\Commerce\Builders\CartBuilder;
use Karnoweb\Commerce\Builders\CheckoutBuilder;
use Karnoweb\Commerce\Builders\PaymentBuilder;
use Karnoweb\Commerce\Builders\RefundBuilder;
use Karnoweb\Commerce\Builders\WalletBuilder;

/**
 * @method static mixed config(?string $key = null, mixed $default = null)
 * @method static class-string<\Illuminate\Database\Eloquent\Model> model(string $key)
 * @method static \Illuminate\Database\Eloquent\Model newModel(string $key)
 * @method static CartBuilder cart() Start building a user's cart (fluent API).
 * @method static CheckoutBuilder checkout() Start placing an order from a cart, or attach an invoice to one (fluent API).
 * @method static PaymentBuilder payment() Start initiating a payment, or confirm a gateway outcome (fluent API).
 * @method static RefundBuilder refund() Start processing a refund (fluent API).
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
