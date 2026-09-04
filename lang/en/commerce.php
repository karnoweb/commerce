<?php

declare(strict_types=1);

return [
    'name' => 'Commerce',

    'messages' => [
        'idempotency_conflict' => 'Idempotency key [:key] was already used with a different request payload.',
        'cannot_checkout_empty_cart' => 'Cannot check out: the cart has no items.',
        'cannot_confirm_already_paid_payment' => 'Cannot confirm: this payment has already been paid.',
        'cannot_pay_cancelled_order' => 'Cannot initiate payment: this order has been cancelled.',
        'refund_amount_exceeds_paid_amount' => 'Refund amount (:requested) exceeds the amount available to refund (:available).',
    ],
];
