<?php

return [
    'approve_instant_confirmation' => 'This approves the withdrawal and sends a real YDPAY payout immediately. Funds stay locked; only verified YDPAY status 2 confirms payment.',
    'deposit_statuses' => [
        '0' => '0 · Initialized (unpaid)',
        '1' => '1 · Awaiting payment',
        '2' => '2 · Paid',
        '3' => '3 · Paid; notification acknowledged',
        '4' => '4 · Refund reported — review required',
        '-1' => '-1 · Failed',
        '-2' => '-2 · Closed',
        'create_pending' => 'Creation unconfirmed — sync required',
        'pending' => 'Awaiting payment',
        'processing' => 'Processing (unpaid)',
        'completed' => 'Paid',
        'failed' => 'Failed',
        'closed' => 'Closed',
        'refund_review' => 'Refund reported — review required',
        'sandbox_review' => 'Sandbox detected — review required',
    ],
    'payout_statuses' => [
        '0' => '0 · Pending',
        '1' => '1 · Processing',
        '2' => '2 · Paid',
        '3' => '3 · Failed',
        '4' => '4 · Awaiting provider review',
        '5' => '5 · Awaiting merchant confirmation',
        '6' => '6 · Refund reported — review required',
    ],
    'review_title' => 'Payment requires review',
    'review_help' => 'The provider reported a refund or a test payment. Please contact support; this is not a confirmed payment.',
];
