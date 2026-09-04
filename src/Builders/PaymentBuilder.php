<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Builders;

use DateTimeInterface;
use InvalidArgumentException;
use Karnoweb\Commerce\Enums\PaymentTypeEnum;
use Karnoweb\Commerce\Models\Invoice;
use Karnoweb\Commerce\Models\Order;
use Karnoweb\Commerce\Models\Payment;
use Karnoweb\Commerce\Services\PaymentService;

/**
 * Fluent entry point for the payment lifecycle: initiate() records a
 * PENDING payment against an invoice (invoice is mandatory — payments
 * always settle a bill); confirm() records a gateway outcome the host
 * already obtained (Commerce never calls a gateway itself).
 *
 * @example
 * $payment = Commerce::payments()
 *     ->forInvoice($invoice)
 *     ->forOrder($order)
 *     ->methodId(1)
 *     ->type(PaymentTypeEnum::ONLINE)
 *     ->amount((int) $invoice->amount)
 *     ->idempotencyKey('pay:invoice:'.$invoice->id.':attempt:1')
 *     ->initiate();
 *
 * Commerce::payments()->confirm(payment: $payment, gateway: 'zarinpal', refId: 'REF-123');
 */
class PaymentBuilder
{
    private ?Invoice $invoice = null;

    private ?Order $order = null;

    private int|string|null $methodId = null;

    private PaymentTypeEnum|string $type = PaymentTypeEnum::ONLINE;

    private int|float|null $amount = null;

    private ?string $idempotencyKey = null;

    public function __construct(private readonly PaymentService $paymentService) {}

    /** Required before initiate() — a payment always settles an invoice. */
    public function forInvoice(Invoice $invoice): self
    {
        $this->invoice = $invoice;

        return $this;
    }

    /** Optional denormalized order link (null for a standalone-invoice payment). */
    public function forOrder(Order $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function methodId(int|string $methodId): self
    {
        $this->methodId = $methodId;

        return $this;
    }

    public function type(PaymentTypeEnum|string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function amount(int|float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    /** Optional DB-unique key for safe retries of initiate(). */
    public function idempotencyKey(string $key): self
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    /**
     * Create a PENDING payment. Retry-safe when idempotencyKey() is set: a
     * second call with the same key and payload returns the same payment.
     */
    public function initiate(): Payment
    {
        if ($this->invoice === null || $this->amount === null) {
            throw new InvalidArgumentException('PaymentBuilder::initiate() requires forInvoice() and amount() before use.');
        }

        return $this->paymentService->initiate(
            $this->invoice,
            $this->order,
            $this->methodId,
            $this->type,
            $this->amount,
            $this->idempotencyKey,
        );
    }

    /**
     * Record a gateway outcome the host already obtained: PENDING -> PAID.
     * Safe to call twice with the same $trackingCode.
     *
     * @param  array<string, mixed>  $gatewayPayload
     */
    public function confirm(
        Payment $payment,
        string $gateway,
        ?string $refId = null,
        ?string $trackingCode = null,
        ?DateTimeInterface $paidAt = null,
        array $gatewayPayload = [],
    ): Payment {
        return $this->paymentService->confirm($payment, $gateway, $refId, $trackingCode, $paidAt, $gatewayPayload);
    }
}
