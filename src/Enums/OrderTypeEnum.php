<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Enums;

enum OrderTypeEnum: string
{
    case SALE = 'sale';
    case SALE_RETURN = 'sale_return';
    case PURCHASE = 'purchase';
    case PURCHASE_RETURN = 'purchase_return';
}
