<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Enums;

enum PaymentTypeEnum: string
{
    case ONLINE = 'online';
    case CASH = 'cash';
    case CARD_TO_CARD = 'card_to_card';
    case BANK = 'bank';
    case WALLET = 'wallet';
}
