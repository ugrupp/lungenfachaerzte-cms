<?php

use craft\filters\Cors;
use craft\helpers\App;

/**
 * Web Application Config
 *
 * Applies only to HTTP (web) requests — not CLI/console.
 * Configures CORS so the TanStack Start frontend can call the GraphQL API.
 *
 * PRIMARY_SITE_URL must be the front-end origin (e.g. http://localhost:3000
 * in local dev, or the Netlify production URL in production).
 *
 * @link https://craftcms.com/docs/5.x/reference/config/app.html
 */
return [
    'as corsFilter' => [
        'class' => Cors::class,
        'cors' => [
            'Origin' => [
                App::env('PRIMARY_SITE_URL'),
            ],
            'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
            'Access-Control-Request-Headers' => ['*'],
            'Access-Control-Allow-Credentials' => true,
            'Access-Control-Max-Age' => 86400,
            'Access-Control-Expose-Headers' => [],
        ],
    ],
];
