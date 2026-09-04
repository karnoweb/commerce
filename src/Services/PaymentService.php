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
 * outcome back here via confirm().
 */
class PaymentService
{
    use ResolvesConfiguredModels;

    /**
     * Create a PENDING payment for an order (optionally tied to an invoice).
     *
     * @throws CannotPayCancelledOrder
     * @throws IdempotencyConflict
     */
    public function initiate(
        Order $order,
        ?Invoice $invoice,
        int|string|null $paymentMethodId,
        PaymentTypeEnum|string $type,
        int|float $amount,
        ?string $idempotencyKey = null,
    ): Payment {
        return DB::transaction(function () use ($order, $invoice, $paymentMethodId, $type, $amount, $idempotencyKey): Payment {
            if ($idempotencyKey !== null) {
                $existing = $this->findPaymentByIdempotencyKey($idempotencyKey);

                if ($existing !== null) {
                    $this->assertSameInitiatePayload($existing, $order, $amount, $idempotencyKey);

                    return $existing;
                }
            }

            if ($order->status === OrderStatusEnum::CANCELLED) {
                throw new CannotPayCancelledOrder($order->id);
            }

            $paymentClass = static::model('payment');

            $payment = $paymentClass::create([
                'user_id' => $order->user_id,
                'branch_id' => $order->branch_id ?? null,
                'order_id' => $order->id,
                'invoice_id' => $invoice?->id,
                'idempotency_key' => $idempotencyKey,
                'payment_method_id' => $paymentMethodId,
                'amount' => $amount,
                'type' => $type instanceof PaymentTypeEnum ? $type : PaymentTypeEnum::from($type),
                'status' => PaymentStatusEnum::PENDING,
            ]);

            CommerceEventDispatcher::dispatch(new PaymentInitiated(
                orderId: $order->id,
                paymentId: $payment->id,
                amount: $amount,
                userId: $order->user_id,
            ));

            return $payment;
        });
    }

    /**
     * Record a gateway outcome reported by the host: PENDING -> PAID.
     * Creates a Transaction, marks the order PAID and the invoice `paid`,
     * then dispatches OrderPaid + InvoiceFullyPaid after commit.
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

                if ($existingTransaction !== null && (string) $existingTransaction->order_id === (string) $payment->order_id) {
                    return $payment;
                }

                throw new CannotConfirmAlreadyPaidPayment($payment->id);
            }

            $payment->update([
                'status' => PaymentStatusEnum::PAID,
                'extra_attributes' => array_merge($payment->extra_attributes ?? [], [
                    'gateway' => $gateway,
                    'ref_id' => $refId,
                    'tracking_code' => $trackingCode,
                ]),
            ]);

            $transactionClass = static::model('transaction');

            $transactionClass::create([
                'user_id' => $payment->user_id,
                'payment_method_id' => $payment->payment_method_id,
                'order_id' => $payment->order_id,
                'type' => 'payment',
                'amount' => $payment->amount,
                'status' => 'paid',
                'ref_id' => $refId,
                'tracking_code' => $trackingCode,
                'gateway_response' => $gatewayPayload,
                'paid_at' => $paidAt ?? now(),
                'extra_attributes' => ['gateway' => $gateway],
            ]);

            $order = $payment->order_id !== null ? static::model('order')::find($payment->order_id) : null;

            if ($order !== null) {
                $order->update(['status' => OrderStatusEnum::PAID, 'paid_at' => $paidAt ?? now()]);

                CommerceEventDispatcher::dispatch(new OrderPaid(
                    orderId: $order->id,
                    userId: $order->user_id,
                ));
            }

            $invoice = $payment->invoice_id !== null ? static::model('invoice')::find($payment->invoice_id) : null;

            if ($invoice !== null) {
                $invoice->update(['status' => 'paid']);

                CommerceEventDispatcher::dispatch(new InvoiceFullyPaid(
                    invoiceId: $invoice->id,
                    orderId: $invoice->order_id,
                ));
            }

            return $payment->refresh();
        });
    }

    private function findPaymentByIdempotencyKey(string $key): ?Payment
    {
        $paymentClass = static::model('payment');

        return $paymentClass::query()->where('idempotency_key', $key)->first();
    }

    private function assertSameInitiatePayload(Payment $existing, Order $order, int|float $amount, string $idempotencyKey): void
    {
        $sameOrder = (string) $existing->order_id === (string) $order->id;
        $sameAmount = (float) $existing->amount === (float) $amount;

        if (! $sameOrder || ! $sameAmount) {
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
