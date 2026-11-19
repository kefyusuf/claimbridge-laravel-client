<?php

declare(strict_types=1);

return [

    'bundle_path' => env('CLAIMBRIDGE_BUNDLE_PATH', null),

    'transport' => [
        // 'fake' (default, sandbox-verbatim fixtures) | 'sandbox-http'
        'driver' => env('CLAIMBRIDGE_DRIVER', 'fake'),

        'sandbox_url' => env('CLAIMBRIDGE_SANDBOX_URL', 'http://127.0.0.1:8123'),
    ],

    // Responses used when the driver is 'fake' (sandbox-verbatim contracts).
    'fake' => [
        'responses' => [],
    ],

];
