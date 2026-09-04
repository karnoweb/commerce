<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Services;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Karnoweb\Commerce\Enums\OrderStatusEnum;
use Karnoweb\Commerce\Enums\PaymentStatusEnum;
use Karnoweb\Commerce\Enums\PaymentTypeEnum;
use Karnoweb\Commerce\Events\InvoiceFullyPaid;
use Karnoweb\Commerce\Events\OrderPaid;
use Karnoweb\Commerce\Events\PaymentConfirmed;
use Karnoweb\Commerce\Events\PaymentInitiated;
use Karnoweb\Commerce\Exceptions\CannotConfirmAlreadyPaidPayment;
use Karnoweb\Commerce\Exceptions\CannotPayCancelledOrder;
use Karnoweb\Commerce\Exceptions\IdempotencyConflict;
use Karnoweb\Commerce\Models\Invoice;
use Karnoweb\Commerce\Models\Order;
use Karnoweb\Commerce\Models\Payment;
use Karnoweb\Commerce\Support\CommerceEventDispatcher;
use Karnoweb\Commerce\Support\ResolvesConfiguredModels;

/**
 * Canonical payment lifecycle: initiate (PENDING) then confirm (PAID).
 * Commerce never talks to a gateway — the host does that and reports the
 * outcome back here via confirm(). A payment always settles an Invoice
 * (invoice_id required); $order is an optional denormalized convenience —
 * standalone-invoice payments pass null.
 */
class PaymentService
{
    use ResolvesConfiguredModels;

    /**
     * Create a PENDING payment against an invoice (optionally order-bound).
     *
     * @throws CannotPayCancelledOrder
     * @throws IdempotencyConflict
     */
    public function initiate(
        Invoice $invoice,
        ?Order $order,
        int|string|null $paymentMethodId,
        PaymentTypeEnum|string $type,
        int|float $amount,
        ?string $idempotencyKey = null,
    ): Payment {
        return DB::transaction(function () use ($invoice, $order, $paymentMethodId, $type, $amount, $idempotencyKey): Payment {
            if ($idempotencyKey !== null) {
                $existing = $this->findPaymentByIdempotencyKey($idempotencyKey);

                if ($existing !== null) {
                    $this->assertSameInitiatePayload($existing, $invoice, $amount, $idempotencyKey);

                    return $existing;
                }
            }

            if ($order !== null && $order->status === OrderStatusEnum::CANCELLED) {
                throw new CannotPayCancelledOrder($order->id);
            }

            $amountInt = (int) round($amount);

            $paymentClass = static::model('payment');

            /** @var Payment $payment */
            $payment = $paymentClass::create([
                'idempotency_key' => $idempotencyKey,
                'invoice_id' => $invoice->id,
                'order_id' => $order?->id ?? $invoice->order_id,
                'user_id' => $order?->user_id ?? $invoice->user_id,
                'branch_id' => $order?->branch_id ?? $invoice->branch_id,
                'payment_method_id' => $paymentMethodId,
                'amount' => $amountInt,
                'type' => $type instanceof PaymentTypeEnum ? $type : PaymentTypeEnum::from($type),
                'status' => PaymentStatusEnum::PENDING,
            ]);

            CommerceEventDispatcher::dispatch(new PaymentInitiated(
                paymentId: $payment->id,
                invoiceId: $invoice->id,
                orderId: $payment->order_id,
                amount: $amountInt,
                userId: $payment->user_id,
            ));

            return $payment;
        });
    }

    /**
     * Record a gateway outcome reported by the host: PENDING -> PAID.
     * Creates a Transaction (payment_id-keyed), marks the order PAID (if
     * order-bound) and the invoice `paid`, then dispatches PaymentConfirmed
     * (+ OrderPaid/InvoiceFullyPaid) after commit.
     *
     * Safe to call twice with the same $trackingCode (no duplicate
     * Transaction is created); calling it again with a *different*
     * $trackingCode on an already-paid payment throws.
     *
     * @param  array<string, mixed>  $gatewayPayload
     *
     * @throws CannotConfirmAlreadyPaidPayment
     */
    public function confirm(
        Payment $payment,
        string $gateway,
        ?string $refId = null,
        ?string $trackingCode = null,
        ?DateTimeInterface $paidAt = null,
        array $gatewayPayload = [],
    ): Payment {
        return DB::transaction(function () use ($payment, $gateway, $refId, $trackingCode, $paidAt, $gatewayPayload): Payment {
            $payment->refresh();
            $trackingCode ??= $this->generateTrackingCode();

            if ($payment->status === PaymentStatusEnum::PAID) {
                $existingTransaction = $this->findTransactionByTrackingCode($trackingCode);

                if ($existingTransaction !== null && (string) $existingTransaction->payment_id === (string) $payment->id) {
                    return $payment;
                }

                throw new CannotConfirmAlreadyPaidPayment($payment->id);
            }

            $payment->update(['status' => PaymentStatusEnum::PAID]);

            $transactionClass = static::model('transaction');

            $transactionClass::create([
                'payment_id' => $payment->id,
                'gateway' => $gateway,
                'ref_id' => $refId,
                'tracking_code' => $trackingCode,
                'gateway_response' => $gatewayPayload,
                'paid_at' => $paidAt ?? now(),
            ]);

            $order = $payment->order_id !== null ? static::model('order')::find($payment->order_id) : null;

            if ($order !== null) {
                $order->update(['status' => OrderStatusEnum::PAID, 'paid_at' => $paidAt ?? now()]);

                CommerceEventDispatcher::dispatch(new OrderPaid(
                    orderId: $order->id,
                    userId: $order->user_id,
                ));
            }

            $invoice = static::model('invoice')::find($payment->invoice_id);

            if ($invoice !== null) {
                $invoice->update(['status' => 'paid']);

                CommerceEventDispatcher::dispatch(new InvoiceFullyPaid(
                    invoiceId: $invoice->id,
                    orderId: $invoice->order_id,
                ));
            }

            CommerceEventDispatcher::dispatch(new PaymentConfirmed(
                paymentId: $payment->id,
                invoiceId: $payment->invoice_id,
                orderId: $payment->order_id,
                amount: $payment->amount,
                userId: $payment->user_id,
            ));

            return $payment->refresh();
        });
    }

    private function findPaymentByIdempotencyKey(string $key): ?Payment
    {
        $paymentClass = static::model('payment');

        return $paymentClass::query()->where('idempotency_key', $key)->first();
    }

    private function assertSameInitiatePayload(Payment $existing, Invoice $invoice, int|float $amount, string $idempotencyKey): void
    {
        $sameInvoice = (string) $existing->invoice_id === (string) $invoice->id;
        $sameAmount = (int) $existing->amount === (int) round($amount);

        if (! $sameInvoice || ! $sameAmount) {
            throw new IdempotencyConflict($idempotencyKey);
        }
    }

    private function findTransactionByTrackingCode(string $trackingCode): ?object
    {
        $transactionClass = static::model('transaction');

        return $transactionClass::query()->where('tracking_code', $trackingCode)->first();
    }

    private function generateTrackingCode(): string
    {
        return 'TRK-'.Str::upper(Str::random(12));
    }
}
