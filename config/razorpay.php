<?php

return [
    'key_id'         => env('RAZORPAY_KEY_ID'),
    'key_secret'     => env('RAZORPAY_KEY_SECRET'),
    'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    'currency'       => env('RAZORPAY_CURRENCY', 'INR'),

    // Shared secret for admin-only endpoints (refunds). Sent as X-Admin-Key header.
    'admin_api_key'  => env('ADMIN_API_KEY'),
];
