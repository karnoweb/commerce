<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Enums;

enum OrderStatusEnum: string
{
    case CART = 'cart';
    case PENDING = 'pending';
    case PAID = 'paid';
    case PROCESSING = 'processing';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
    case EXPIRED = 'expired';
}
