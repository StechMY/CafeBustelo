<?php

return [
    // YDPAY uses this deployment-managed HTTP(S) proxy; never falls back to direct traffic.
    'outbound_proxy' => env('THIRD_PARTY_OUTBOUND_PROXY'),
];
