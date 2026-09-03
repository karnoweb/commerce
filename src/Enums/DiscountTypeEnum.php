<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Enums;

enum DiscountTypeEnum: string
{
    case PERCENTAGE = 'percentage';
    case AMOUNT = 'amount';
}
