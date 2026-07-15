<?php

declare(strict_types=1);

return array(
    'disk' => env('KCFINDER_DISK', 'public'),
    'url_prefix' => env('KCFINDER_URL_PREFIX'),
    'temporary_url_ttl' => null,
    'gate_ability' => 'kcfinder.select',
    'browser_url' => env('KCFINDER_BROWSER_URL', '/vendor/kcfinder/browse.php'),
);
