<?php

// The public demo: a fictional company anyone can sign in to, rebuilt from scratch every night. Off by default; turn
// it on only on a server that holds nothing but the demo, because the nightly reset wipes the whole database.
return [
    'enabled' => (bool) env('HRIS_DEMO', false),

    // Asia/Manila time of the nightly rebuild (php artisan hris:demo --fresh).
    'reset_at' => env('HRIS_DEMO_RESET_AT', '03:00'),

    // Shared by the three demo logins, and shown on the sign-in page while the demo is on.
    'password' => env('HRIS_DEMO_PASSWORD', 'demo1234'),
];
