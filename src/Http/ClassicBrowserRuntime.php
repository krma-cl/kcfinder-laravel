<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Http;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use KCFinder\Contract\OperationObserverInterface;
use RuntimeException;

final class ClassicBrowserRuntime
{
    public function __construct(
        private readonly Repository $config,
        private readonly FilesystemFactory $filesystems,
        private readonly OperationObserverInterface $observer,
        private readonly NativeSessionInitializer $sessions
    ) {
    }

    /** @return array<string, mixed> */
    public function prepare(): array
    {
        $http = (array) $this->config->get('kcfinder.http', array());
        $this->sessions->initialize((array) ($http['session'] ?? array()));

        $diskName = (string) $this->config->get('kcfinder.disk', 'public');
        $disk = $this->filesystems->disk($diskName);
        if (!$disk instanceof FilesystemAdapter) {
            throw new RuntimeException('The classic browser requires a Laravel FilesystemAdapter.');
        }

        try {
            $uploadDir = $disk->path('');
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'The classic browser HTTP bridge requires a local filesystem disk.',
                0,
                $exception
            );
        }

        $runtime = (array) ($http['runtime'] ?? array());
        $runtime['disabled'] = false;
        $runtime['uploadDir'] = $uploadDir;
        $runtime['uploadURL'] = (string) ($runtime['uploadURL'] ?? $this->config->get('kcfinder.url_prefix', '/storage'));
        $runtime['_operationObserver'] = $this->observer;
        $runtime['_sessionCsrf'] = true;

        $_SESSION['KCFINDER'] = array_replace(
            is_array($_SESSION['KCFINDER'] ?? null) ? $_SESSION['KCFINDER'] : array(),
            array_filter(
                $runtime,
                static fn (string $key): bool => !str_starts_with($key, '_'),
                ARRAY_FILTER_USE_KEY
            )
        );
        $GLOBALS['KCFINDER_RUNTIME_CONFIG'] = $runtime;

        return $runtime;
    }
}
