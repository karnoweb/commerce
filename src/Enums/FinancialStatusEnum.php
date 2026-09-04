<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Enums;

/**
 * Strict financial lifecycle for an order. Workflow labels (cooking,
 * shipped, ...) live on `orders.workflow_status` and are never validated
 * here. Allowed transitions are enforced by FinancialStateMachine:
 * pending → paid | cancelled; paid → refunded (full return only).
 */
enum FinancialStatusEnum: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
}
