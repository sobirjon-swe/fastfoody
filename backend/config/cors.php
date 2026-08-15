<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The React SPA runs on its own origin and talks to this API with a bearer
    | token, so only the frontend origins listed in FRONTEND_URL are allowed
    | and no credentials (cookies) are exchanged.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => explode(',', (string) env('FRONTEND_URL', 'http://localhost:5173')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
