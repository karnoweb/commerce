<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Support;

use Karnoweb\Commerce\Contracts\InvoiceNumberGeneratorContract;

/**
 * Default invoice-number strategy: INV-{year}-{branch?}-{sequence} from
 * `document_sequences` (key=invoice_number). Format is
 * config('commerce.numbers.invoice.format').
 */
final class SequentialInvoiceNumberGenerator extends SequentialDocumentNumberGenerator implements InvoiceNumberGeneratorContract
{
    protected function sequenceKey(): string
    {
        return 'invoice_number';
    }

    protected function format(): string
    {
        return (string) config('commerce.numbers.invoice.format', 'INV-{year}-{branch}-{sequence}');
    }

    protected function padding(): int
    {
        return (int) config('commerce.numbers.invoice.padding', 6);
    }
}
