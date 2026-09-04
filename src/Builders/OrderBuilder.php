<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Builders;

use Karnoweb\Commerce\Models\Order;
use Karnoweb\Commerce\Services\OrderService;

/**
 * Fluent entry point for post-checkout order mutations. Financial
 * status is not set here — confirm()/returns() drive that through
 * FinancialStateMachine. workflow_status is a free-form host label.
 *
 * @example
 * Commerce::orders()->setWorkflowStatus($order->id, 'cooking');
 * Commerce::orders()->cancel($order->id); // pending → cancelled only
 */
class OrderBuilder
{
    public function __construct(private readonly OrderService $orderService) {}

    /** Store a free-form workflow label. Does not touch financial_status. */
    public function setWorkflowStatus(int|string $orderId, string $status): Order
    {
        return $this->orderService->setWorkflowStatus($orderId, $status);
    }

    /** pending → cancelled. Throws InvalidFinancialTransition otherwise. */
    public function cancel(int|string $orderId): Order
    {
        return $this->orderService->cancel($orderId);
    }
}
