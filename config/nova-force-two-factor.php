<?php

declare(strict_types=1);

return [

    // The master `enabled` switch is shared across adapters and now lives in the
    // base package: config/laravel-force-two-factor.php (env FORCE_TWO_FACTOR_ENABLED).

    /*
    |--------------------------------------------------------------------------
    | Automatic middleware registration
    |--------------------------------------------------------------------------
    |
    | The service provider injects the middleware into Nova's stack for you. Set
    | this to false to wire it up yourself (e.g. at a specific position in
    | config/nova.php or the "nova" middleware group).
    |
    */

    'auto_register_middleware' => (bool) env('NOVA_FORCE_TWO_FACTOR_AUTO_MIDDLEWARE', true),

    /*
    |--------------------------------------------------------------------------
    | Exceptions
    |--------------------------------------------------------------------------
    |
    | Requests matching any of these are let through even when the admin has not
    | enrolled. Both lists accept the wildcards Laravel's routeIs()/is()
    | understand, so exact route names, route-name patterns, exact URLs and URL
    | patterns are all covered.
    |
    | Nova's assets, logout and the "User Security" enrolment subtree are always
    | allowed (they cannot be blocked without locking admins out) and do not
    | need to be listed here.
    |
    */

    'except' => [

        // Matched with $request->routeIs(...).
        'routes' => [
            // Let an expired password still be changed (bbs-lab/nova-password-rotation).
            'nova-password-rotation.expired.*',
        ],

        // Matched with $request->is(...) against the URL path.
        'paths' => [
            //
        ],

    ],

];
