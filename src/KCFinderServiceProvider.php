<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\ServiceProvider;
use KCFinder\Application\FileSelectionService;
use KCFinder\Contract\AuthorizationInterface;
use KCFinder\Contract\FileMetadataProviderInterface;
use KCFinder\Contract\UrlResolverInterface;
use Krma\KCFinder\Laravel\Contracts\ActorResolverInterface;
use Krma\KCFinder\Laravel\Contracts\ChecksumProviderInterface;
use Krma\KCFinder\Laravel\Contracts\PreviewUrlResolverInterface;
use Krma\KCFinder\Laravel\Contracts\SelectedUrlResolverInterface;
use RuntimeException;

final class KCFinderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/kcfinder.php', 'kcfinder');

        $this->app->singleton(SelectedUrlResolverInterface::class, function ($app): StorageUrlResolver {
            $config = $app['config']->get('kcfinder', array());
            $selected = $config['urls']['selected'] ?? array();
            $prefix = $selected['prefix'] ?? $config['url_prefix'] ?? null;
            $ttl = $selected['temporary_url_ttl'] ?? $config['temporary_url_ttl'] ?? null;
            return new StorageUrlResolver(
                $this->disk($app),
                is_string($prefix) ? $prefix : null,
                $ttl === null || $ttl === '' ? null : (int) $ttl
            );
        });

        $this->app->alias(SelectedUrlResolverInterface::class, UrlResolverInterface::class);

        $this->app->singleton(PreviewUrlResolverInterface::class, function ($app): StorageUrlResolver {
            $config = $app['config']->get('kcfinder', array());
            $preview = $config['urls']['preview'] ?? array();
            $selected = $config['urls']['selected'] ?? array();
            $prefix = $preview['prefix'] ?? $selected['prefix'] ?? $config['url_prefix'] ?? null;
            $ttl = $preview['temporary_url_ttl']
                ?? $selected['temporary_url_ttl']
                ?? $config['temporary_url_ttl']
                ?? null;
            return new StorageUrlResolver(
                $this->disk($app),
                is_string($prefix) ? $prefix : null,
                $ttl === null || $ttl === '' ? null : (int) $ttl
            );
        });

        $this->app->singleton(FileMetadataProviderInterface::class, function ($app): StorageMetadataProvider {
            return new StorageMetadataProvider(
                $this->disk($app),
                $app->make(SelectedUrlResolverInterface::class)
            );
        });

        $this->app->singleton(AuthorizationInterface::class, function ($app): GateAuthorization {
            return new GateAuthorization(
                $app->make(Gate::class),
                (string) $app['config']->get('kcfinder.gate_ability', 'kcfinder.select')
            );
        });

        $this->app->singleton(FileSelectionService::class, fn ($app): FileSelectionService => new FileSelectionService(
            $app->make(FileMetadataProviderInterface::class),
            $app->make(AuthorizationInterface::class)
        ));

        $this->app->singleton(ActorResolverInterface::class, fn ($app): AuthActorResolver => new AuthActorResolver(
            $app->make(AuthFactory::class)
        ));

        $this->app->singleton(ChecksumProviderInterface::class, function ($app): StorageChecksumProvider {
            $algorithm = $app['config']->get('kcfinder.events.checksum_algorithm', 'sha256');
            return new StorageChecksumProvider(
                $this->disk($app),
                is_string($algorithm) && $algorithm !== '' ? $algorithm : null
            );
        });

        $this->app->singleton(KCFinderOperationReporter::class, fn ($app): KCFinderOperationReporter => new KCFinderOperationReporter(
            $app->make(FileMetadataProviderInterface::class),
            $app->make(AuthorizationInterface::class),
            $app->make(Dispatcher::class),
            $app->make(ActorResolverInterface::class),
            $app->make(ChecksumProviderInterface::class)
        ));

        $this->app->singleton(KCFinderManager::class, fn ($app): KCFinderManager => new KCFinderManager(
            $app->make(FileSelectionService::class),
            $app->make(Dispatcher::class),
            $app->make(KCFinderOperationReporter::class),
            $app->make(AuthorizationInterface::class),
            $app->make(PreviewUrlResolverInterface::class)
        ));
    }

    public function boot(): void
    {
        $this->publishes(array(
            __DIR__ . '/../config/kcfinder.php' => config_path('kcfinder.php'),
        ), 'kcfinder-config');
    }

    private function disk(mixed $app): FilesystemAdapter
    {
        $diskName = (string) $app['config']->get('kcfinder.disk', 'public');
        $disk = $app->make(FilesystemFactory::class)->disk($diskName);
        if (!$disk instanceof FilesystemAdapter) {
            throw new RuntimeException('The configured KCFinder disk must use Laravel FilesystemAdapter.');
        }
        return $disk;
    }
}
