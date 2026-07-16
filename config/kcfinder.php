<?php

declare(strict_types=1);

return array(
    'disk' => env('KCFINDER_DISK', 'public'),
    'url_prefix' => env('KCFINDER_URL_PREFIX'),
    'temporary_url_ttl' => null,
    'urls' => array(
        'selected' => array(
            'prefix' => env('KCFINDER_SELECTED_URL_PREFIX', env('KCFINDER_URL_PREFIX')),
            'temporary_url_ttl' => env('KCFINDER_SELECTED_URL_TTL'),
        ),
        'preview' => array(
            'prefix' => env('KCFINDER_PREVIEW_URL_PREFIX', env('KCFINDER_URL_PREFIX')),
            'temporary_url_ttl' => env('KCFINDER_PREVIEW_URL_TTL'),
        ),
    ),
    'events' => array(
        'checksum_algorithm' => env('KCFINDER_CHECKSUM_ALGORITHM', 'sha256'),
    ),
    'gate_ability' => 'kcfinder.select',
    'browser_url' => env('KCFINDER_BROWSER_URL', '/vendor/kcfinder/browse.php'),
);
