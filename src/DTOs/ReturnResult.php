<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\DTOs;

use Karnoweb\Commerce\Models\OrderReturn;
use Karnoweb\Commerce\Models\Wallet;
use Karnoweb\Commerce\Models\WalletTransaction;

/**
 * Returned by ReturnService::processToWallet() / ReturnBuilder::
 * finalizeRefundToWalletResult(): the persisted return plus the wallet
 * credit that settled it.
 */
final readonly class ReturnResult
{
    public function __construct(
        public OrderReturn $orderReturn,
        public Wallet $wallet,
        public WalletTransaction $walletTransaction,
    ) {}
}
