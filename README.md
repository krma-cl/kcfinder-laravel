# KCFinder for Laravel

Official Laravel adapter for [`krma-cl/kcfinder`](https://github.com/krma-cl/kcfinder-Resurrected). It connects the framework filesystem, authorization gate and event dispatcher to KCFinder's framework-independent selector contract.

## Requirements

- PHP 8.2 or newer.
- Laravel 12 or 13.
- A deployed KCFinder browser from `krma-cl/kcfinder`.

## Installation

```bash
composer require krma-cl/kcfinder-laravel
php artisan vendor:publish --tag=kcfinder-config
```

Configure the disk and browser URL in `.env`:

```dotenv
KCFINDER_DISK=public
KCFINDER_URL_PREFIX=/storage
KCFINDER_BROWSER_URL=/vendor/kcfinder/browse.php
```

Define the authorization rule. The callback receives the operation and logical path:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('kcfinder.select', static function ($user, string $operation, string $path): bool {
    return $user->can('manage-files');
});
```

Resolve a selected file as the stable JSON-compatible descriptor:

```php
use Krma\KCFinder\Laravel\KCFinderManager;

$file = app(KCFinderManager::class)->select('/01-actos/DO-20130614.pdf');
return response()->json($file);
```

The result contains `name`, `path`, `url`, `mime` and `size`. A `FileSelected` event is dispatched after an authorized selection.

The adapter does not copy or publish the legacy browser automatically. This keeps Docker and Laravel optional and preserves KCFinder's traditional deployment model. Follow the [core installation guide](https://github.com/krma-cl/kcfinder-Resurrected#installation) for the browser itself.

## Development

```bash
composer install
composer check
```
