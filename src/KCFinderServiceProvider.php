<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\ServiceProvider;
use KCFinder\Application\FileSelectionService;
use KCFinder\Contract\AuthorizationInterface;
use KCFinder\Contract\FileMetadataProviderInterface;
use KCFinder\Contract\UrlResolverInterface;

final class KCFinderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/kcfinder.php', 'kcfinder');

        $this->app->singleton(UrlResolverInterface::class, function ($app): StorageUrlResolver {
            $config = $app['config']->get('kcfinder', array());
            $disk = $app->make(FilesystemFactory::class)->disk((string) ($config['disk'] ?? 'public'));
            if (!$disk instanceof FilesystemAdapter) {
                throw new \RuntimeException('The configured KCFinder disk must use Laravel FilesystemAdapter.');
            }
            $ttl = $config['temporary_url_ttl'] ?? null;
            return new StorageUrlResolver($disk, $config['url_prefix'] ?? null, $ttl === null ? null : (int) $ttl);
        });

        $this->app->singleton(FileMetadataProviderInterface::class, function ($app): StorageMetadataProvider {
            $diskName = (string) $app['config']->get('kcfinder.disk', 'public');
            $disk = $app->make(FilesystemFactory::class)->disk($diskName);
            if (!$disk instanceof FilesystemAdapter) {
                throw new \RuntimeException('The configured KCFinder disk must use Laravel FilesystemAdapter.');
            }
            return new StorageMetadataProvider($disk, $app->make(UrlResolverInterface::class));
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

        $this->app->singleton(KCFinderManager::class, fn ($app): KCFinderManager => new KCFinderManager(
            $app->make(FileSelectionService::class),
            $app->make(Dispatcher::class)
        ));
    }

    public function boot(): void
    {
        $this->publishes(array(
            __DIR__ . '/../config/kcfinder.php' => config_path('kcfinder.php'),
        ), 'kcfinder-config');
    }
}
