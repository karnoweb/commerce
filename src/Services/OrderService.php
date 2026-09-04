<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Services;

use Karnoweb\Commerce\Enums\FinancialStatusEnum;
use Karnoweb\Commerce\Models\Order;
use Karnoweb\Commerce\Support\FinancialStateMachine;
use Karnoweb\Commerce\Support\ResolvesConfiguredModels;

/**
 * Order-level mutations that are not checkout: free-form workflow
 * labels, and the strict financial transitions used by payment/return
 * services. Hosts set workflow via Commerce::orders()->setWorkflowStatus().
 */
class OrderService
{
    use ResolvesConfiguredModels;

    public function setWorkflowStatus(int|string $orderId, string $status): Order
    {
        $order = $this->findOrder($orderId);
        $order->update(['workflow_status' => $status]);

        return $order->refresh();
    }

    public function cancel(int|string $orderId): Order
    {
        return $this->transitionTo($this->findOrder($orderId), FinancialStatusEnum::CANCELLED);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function transitionTo(Order $order, FinancialStatusEnum $to, array $extra = []): Order
    {
        $order->refresh();

        $from = $order->financial_status ?? FinancialStatusEnum::PENDING;
        FinancialStateMachine::assertCanTransition($from, $to);

        if ($from === $to) {
            return $order;
        }

        $order->update(array_merge(['financial_status' => $to], $extra));

        return $order->refresh();
    }

    private function findOrder(int|string $orderId): Order
    {
        $orderClass = static::model('order');

        /** @var Order $order */
        $order = $orderClass::query()->findOrFail($orderId);

        return $order;
    }
}
