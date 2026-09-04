<?php

declare(strict_types=1);

namespace Karnoweb\Commerce;

use Illuminate\Support\Traits\Macroable;
use Karnoweb\Commerce\Builders\CartBuilder;
use Karnoweb\Commerce\Builders\CheckoutBuilder;
use Karnoweb\Commerce\Builders\PaymentBuilder;
use Karnoweb\Commerce\Builders\RefundBuilder;
use Karnoweb\Commerce\Builders\WalletBuilder;
use Karnoweb\Commerce\Services\CartService;
use Karnoweb\Commerce\Services\CheckoutService;
use Karnoweb\Commerce\Services\PaymentService;
use Karnoweb\Commerce\Services\RefundService;
use Karnoweb\Commerce\Services\WalletService;
use Karnoweb\Commerce\Support\ResolvesConfiguredModels;

class Commerce
{
    use Macroable;
    use ResolvesConfiguredModels;

    public function __construct(
        protected CartService $cartService,
        protected CheckoutService $checkoutService,
        protected PaymentService $paymentService,
        protected RefundService $refundService,
        protected WalletService $walletService,
    ) {}

    public function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return config('commerce');
        }

        return config('commerce.'.$key, $default);
    }

    /**
     * Fresh cart builder each call — state is never shared across constructions.
     */
    public function cart(): CartBuilder
    {
        return new CartBuilder($this->cartService);
    }

    /**
     * Fresh checkout builder each call — state is never shared across constructions.
     */
    public function checkout(): CheckoutBuilder
    {
        return new CheckoutBuilder($this->checkoutService);
    }

    /**
     * Fresh payment builder each call — state is never shared across constructions.
     */
    public function payment(): PaymentBuilder
    {
        return new PaymentBuilder($this->paymentService);
    }

    /**
     * Fresh refund builder each call — state is never shared across constructions.
     */
    public function refund(): RefundBuilder
    {
        return new RefundBuilder($this->refundService);
    }

    /**
     * Fresh wallet builder each call — state is never shared across constructions.
     */
    public function wallet(): WalletBuilder
    {
        return new WalletBuilder($this->walletService);
    }
}
