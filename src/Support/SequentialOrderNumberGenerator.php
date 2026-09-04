<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Support;

use Karnoweb\Commerce\Contracts\OrderNumberGeneratorContract;

/**
 * Default order-number strategy: ORD-{year}-{branch?}-{sequence} from
 * `document_sequences` (key=order_number). Format is
 * config('commerce.numbers.order.format').
 */
final class SequentialOrderNumberGenerator extends SequentialDocumentNumberGenerator implements OrderNumberGeneratorContract
{
    protected function sequenceKey(): string
    {
        return 'order_number';
    }

    protected function format(): string
    {
        return (string) config('commerce.numbers.order.format', 'ORD-{year}-{branch}-{sequence}');
    }

    protected function padding(): int
    {
        return (int) config('commerce.numbers.order.padding', 6);
    }
}
