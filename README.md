# KCFinder for Laravel

Official Laravel adapter for [`krma-cl/kcfinder`](https://github.com/krma-cl/kcfinder-Resurrected). It connects Laravel filesystem, authorization, URL generation and native events to KCFinder while keeping the browser framework-independent.

## Requirements

- PHP 8.2 or newer.
- Laravel 12 or 13.
- A deployed KCFinder browser from `krma-cl/kcfinder`.

## Installation

```bash
composer require krma-cl/kcfinder-laravel:^1.3
php artisan vendor:publish --tag=kcfinder-config
```

Configure the disk and browser URL in `.env`:

```dotenv
KCFINDER_DISK=public
KCFINDER_URL_PREFIX=/storage
KCFINDER_BROWSER_URL=/kcfinder/browse.php
```

Define the authorization rule. The callback receives the operation and logical path:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('kcfinder.select', static function ($user, string $operation, string $path): bool {
    return $user->can('manage-files');
});
```

Operations include `browse`, `select`, `preview`, `upload`, `edit`, `copy`,
`move`, `rename`, `delete` and `create_directory`.

## Optional authenticated classic browser

Version 1.3 can route the classic browser through Laravel instead of exposing a
PHP entrypoint inside `vendor`. It is disabled by default. Enable it only behind
your application's authenticated middleware:

```dotenv
KCFINDER_HTTP_ENABLED=true
KCFINDER_HTTP_PREFIX=kcfinder
KCFINDER_SESSION_PATH=/absolute/writable/path/kcfinder-sessions
KCFINDER_UPLOAD_URL=/storage
```

```php
// config/kcfinder.php
'http' => [
    'enabled' => true,
    'prefix' => 'kcfinder',
    'middleware' => ['web', 'auth', 'can:manage-files'],
    // ...
],
```

The browser is then available at `/kcfinder/browse.php`. The bridge:

- authorizes the request through the configured Gate;
- starts an isolated native session in a configurable writable directory;
- makes the CSRF token available on the very first request;
- injects the official operation observer without editing `vendor`;
- applies `nosniff`, a same-origin referrer policy and a configurable CSP;
- serves only the known browser entrypoints and safe static asset types.

The classic browser currently requires a local Laravel filesystem disk because
its image editor and legacy file operations use physical paths. S3 and other
remote disks remain supported for descriptors and URL resolvers, but not for
this optional HTTP browser bridge.

Publish static assets without copying executable PHP files:

```bash
composer require krma-cl/kcfinder-bootstrap5-theme:^0.3
php artisan kcfinder:install-assets
php artisan kcfinder:install-assets --force
php artisan kcfinder:clear-cache
```

`kcfinder:clear-cache` removes generated bundles and the `.thumbs` tree. Adjust
the CSP and middleware in configuration when the browser must be embedded in a
different trusted origin.
When the Bootstrap 5 theme package is installed, `kcfinder:install-assets`
detects it and publishes `dist/bootstrap5` automatically. The generated
application manifest records both installed package versions.

## Selecting files

Resolve a selected file as a stable JSON-compatible descriptor:

```php
use Krma\KCFinder\Laravel\Facades\KCFinder;

$file = KCFinder::select('/01-actos/DO-20130614.pdf');

return response()->json($file);
```

```json
{
  "name": "DO-20130614.pdf",
  "path": "/01-actos/DO-20130614.pdf",
  "url": "/storage/01-actos/DO-20130614.pdf",
  "mime": "application/pdf",
  "size": 184320
}
```

A `FileSelected` event is dispatched after an authorized selection.

## Separate preview and selected URLs

Applications no longer need to expose a storage path directly. Preview and final selected URLs can use different prefixes or temporary signed URLs:

```dotenv
KCFINDER_SELECTED_URL_PREFIX=/storage/transparencia
KCFINDER_PREVIEW_URL_PREFIX=/internal/kcfinder/preview
KCFINDER_PREVIEW_URL_TTL=300
```

```php
$previewUrl = KCFinder::previewUrl('/images/photo.jpg');
$selectedUrl = KCFinder::selectedUrl('/images/photo.jpg');
```

For S3-compatible disks, setting a TTL makes the adapter request a temporary URL from Laravel's filesystem driver. If an application needs controller routes, authenticated previews or another strategy, bind the contracts in a service provider:

```php
use Krma\KCFinder\Laravel\Contracts\PreviewUrlResolverInterface;
use Krma\KCFinder\Laravel\Contracts\SelectedUrlResolverInterface;

$this->app->bind(PreviewUrlResolverInterface::class, AuthenticatedPreviewResolver::class);
$this->app->bind(SelectedUrlResolverInterface::class, PublicAssetResolver::class);
```

Both resolvers receive a normalized absolute logical path. Preview resolution is authorized with the `preview` operation before the resolver is called.

## Structured operation responses

Version 1.1 adds a JSON-serializable operation result for upload, edit, move, rename, delete and directory creation. Wire it into the callbacks that already run after a successful KCFinder storage mutation:

```php
use Krma\KCFinder\Laravel\Facades\KCFinder;

$result = KCFinder::reportUploaded('/images/photo.jpg');

return response()->json($result, $result->httpStatus());
```

```json
{
  "success": true,
  "operation": "upload",
  "files": [
    {
      "name": "photo.jpg",
      "path": "/images/photo.jpg",
      "url": "/storage/images/photo.jpg",
      "mime": "image/jpeg",
      "size": 145408
    }
  ],
  "warnings": [],
  "meta": { "version": 1 }
}
```

Failures can retain a stable code, a useful message and their HTTP status:

```php
use Krma\KCFinder\Laravel\Domain\OperationResult;

$result = OperationResult::failure(
    'upload',
    'MIME_NOT_ALLOWED',
    'The detected file type is not allowed.',
    415
);
```

If the file was saved but a secondary catalog operation failed, return a warning instead of a false upload failure:

```php
use Krma\KCFinder\Laravel\Domain\OperationWarning;

$result = KCFinder::reportUploaded('/images/photo.jpg', [
    new OperationWarning(
        'CATALOG_SYNC_FAILED',
        'The file was saved, but catalog synchronization must be retried.'
    ),
]);
```

The adapter does not replace the legacy KCFinder JavaScript response automatically. Structured mode is opt-in and can be introduced endpoint by endpoint without breaking existing installations.

## Automatic classic browser bridge

KCFinder 4.8.1 exposes a neutral operation observer at the exact success points
of the classic browser. The adapter registers `ClassicBrowserBridge` as its
Laravel implementation, so integrations no longer need to call every `report*`
method manually.

When the classic browser runs inside an already bootstrapped Laravel application, connect the observer in KCFinder's `conf/config.local.php`:

```php
use KCFinder\Contract\OperationObserverInterface;

$_LOCALS['_operationObserver'] = app(OperationObserverInterface::class);
```

Do not bootstrap Laravel a second time from `config.local.php`. If `browse.php` is currently a completely independent public script, first expose it through the application's existing authenticated Laravel bootstrap or keep using the explicit `report*` methods until that boundary is available.

The bridge then performs this mapping automatically:

| Classic operation | Laravel event |
| --- | --- |
| upload, drag upload | `FileUploaded` |
| image edit, crop | `FileEdited` |
| move | `FileMoved` |
| copy | `FileCopied` |
| rename | `FileRenamed` |
| delete | `FileDeleted` |
| create directory | `DirectoryCreated` |
| rename directory | `DirectoryRenamed` |
| delete directory | `DirectoryDeleted` |

Move, rename and delete take their authorized snapshot before the filesystem mutation. Bulk operations emit one event for each file that actually succeeds. A listener exception is logged by the core observer boundary and does not turn an already completed filesystem mutation into a false failure response.

Manual `report*` calls remain available for custom JSON endpoints. Do not use both mechanisms for the same operation, or the application will dispatch duplicate events.

## Native Laravel events

The reporter dispatches these events:

| Operation | Event |
| --- | --- |
| upload | `FileUploaded` |
| edit/crop | `FileEdited` |
| move | `FileMoved` |
| copy | `FileCopied` |
| rename | `FileRenamed` |
| delete | `FileDeleted` |
| create directory | `DirectoryCreated` |
| rename directory | `DirectoryRenamed` |
| delete directory | `DirectoryDeleted` |

File events contain the descriptor, SHA-256 checksum and authenticated user. Move and rename events contain both `previous` and `file` snapshots:

```php
use Krma\KCFinder\Laravel\Events\FileMoved;

final class SynchronizeCatalog
{
    public function handle(FileMoved $event): void
    {
        $oldPath = $event->previous->file->path;
        $newPath = $event->file->path;
        $checksum = $event->file->checksum;
        $user = $event->user;
    }
}
```

This allows audit and catalog synchronization without scanning the complete storage tree.

### Correct order for destructive or relocating operations

Take the old snapshot before deleting, moving or renaming, then report only after the storage mutation succeeds:

```php
$before = KCFinder::snapshot('/images/old-name.jpg', 'rename');

// Perform the authorized rename in the existing KCFinder integration.

$result = KCFinder::reportRenamed($before, '/images/new-name.jpg');
return response()->json($result, $result->httpStatus());
```

For deletion:

```php
$deleted = KCFinder::snapshot('/images/photo.jpg', 'delete');

// Delete the file.

$result = KCFinder::reportDeleted($deleted);
```

Checksums are streamed instead of loading the whole file into memory. They can be changed or disabled:

```dotenv
KCFINDER_CHECKSUM_ALGORITHM=sha256
```

Set an empty value to disable checksums.

## Compatibility

The existing `KCFINDER_URL_PREFIX` and `temporary_url_ttl` configuration remain supported. Existing calls to `select()` and the previous two-argument `KCFinderManager` constructor continue to work. The automatic bridge requires KCFinder core 4.6 or newer.

The adapter keeps the HTTP bridge opt-in and never copies executable PHP files
from `vendor`. Traditional standalone KCFinder installations remain supported.

## Maintenance and community

This official KCFinder integration is maintained by [KRMA](https://krmachile.com) together with its community of users and contributors. KRMA provides development, coordination and infrastructure to support the project's continuity.

## Development

```bash
composer install
composer check
```
