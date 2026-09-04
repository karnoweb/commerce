<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Contracts;

/**
 * Produces an invoice_number when InvoiceBuilder / CheckoutBuilder did
 * not pass an explicit override. Bind a different implementation to
 * swap the sequential default.
 */
interface InvoiceNumberGeneratorContract
{
    public function generate(int|string|null $branchId = null, ?int $year = null): string;
}
