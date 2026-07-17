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
    'browser_url' => env('KCFINDER_BROWSER_URL', '/kcfinder/browse.php'),
    'http' => array(
        'enabled' => env('KCFINDER_HTTP_ENABLED', false),
        'prefix' => env('KCFINDER_HTTP_PREFIX', 'kcfinder'),
        'middleware' => array('web', 'auth'),
        'assets_path' => env('KCFINDER_ASSETS_PATH'),
        'headers' => array(
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'Content-Security-Policy' => "default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'; frame-ancestors 'self'",
        ),
        'session' => array(
            'name' => env('KCFINDER_SESSION_NAME', 'KCFINDERSESSID'),
            'save_path' => env('KCFINDER_SESSION_PATH'),
            'cookie_path' => '/',
            'cookie_domain' => '',
            'secure' => env('KCFINDER_SESSION_SECURE', false),
            'same_site' => 'Lax',
        ),
        'runtime' => array(
            'uploadURL' => env('KCFINDER_UPLOAD_URL', env('KCFINDER_URL_PREFIX', '/storage')),
            'theme' => env('KCFINDER_THEME', 'default'),
            'types' => array('files' => ''),
            'allowExts' => '',
            'allowMimeTypes' => '',
        ),
    ),
);
