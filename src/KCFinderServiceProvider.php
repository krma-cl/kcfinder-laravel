<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use KCFinder\Application\FileSelectionService;
use KCFinder\Contract\AuthorizationInterface;
use KCFinder\Contract\FileMetadataProviderInterface;
use KCFinder\Contract\OperationObserverInterface;
use KCFinder\Contract\UrlResolverInterface;
use Krma\KCFinder\Laravel\Contracts\ActorResolverInterface;
use Krma\KCFinder\Laravel\Contracts\ChecksumProviderInterface;
use Krma\KCFinder\Laravel\Contracts\PreviewUrlResolverInterface;
use Krma\KCFinder\Laravel\Contracts\SelectedUrlResolverInterface;
use Krma\KCFinder\Laravel\Console\ClearCacheCommand;
use Krma\KCFinder\Laravel\Console\InstallAssetsCommand;
use Krma\KCFinder\Laravel\Http\ClassicBrowserEntrypoint;
use Krma\KCFinder\Laravel\Http\ClassicBrowserBundles;
use Krma\KCFinder\Laravel\Http\ClassicBrowserRuntime;
use Krma\KCFinder\Laravel\Http\Controllers\ClassicBrowserController;
use Krma\KCFinder\Laravel\Http\NativeSessionInitializer;
use ReflectionClass;
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

        $this->app->singleton(ClassicBrowserBridge::class, fn ($app): ClassicBrowserBridge => new ClassicBrowserBridge(
            $app->make(KCFinderOperationReporter::class)
        ));
        $this->app->alias(ClassicBrowserBridge::class, OperationObserverInterface::class);

        $this->app->singleton(KCFinderManager::class, fn ($app): KCFinderManager => new KCFinderManager(
            $app->make(FileSelectionService::class),
            $app->make(Dispatcher::class),
            $app->make(KCFinderOperationReporter::class),
            $app->make(AuthorizationInterface::class),
            $app->make(PreviewUrlResolverInterface::class)
        ));

        $coreRoot = $this->coreRoot();
        $this->app->singleton(ThemePackageLocator::class);
        $themeRoots = $this->app->make(ThemePackageLocator::class)->roots();
        $config = $this->app->make(ConfigRepository::class);
        $publishedAssetsRoot = $config->get('kcfinder.http.assets_path');
        if (!is_string($publishedAssetsRoot) || $publishedAssetsRoot === '') {
            $publishedAssetsRoot = public_path(
                trim((string) $config->get('kcfinder.http.prefix', 'kcfinder'), '/')
            );
        }
        $this->app->singleton(ClassicBrowserBundles::class, fn (): ClassicBrowserBundles => new ClassicBrowserBundles(
            $coreRoot,
            $themeRoots
        ));
        $this->app->singleton(NativeSessionInitializer::class);
        $this->app->singleton(ClassicBrowserRuntime::class, fn ($app): ClassicBrowserRuntime => new ClassicBrowserRuntime(
            $app->make(ConfigRepository::class),
            $app->make(FilesystemFactory::class),
            $app->make(OperationObserverInterface::class),
            $app->make(NativeSessionInitializer::class),
            $coreRoot,
            $themeRoots
        ));
        $this->app->singleton(ClassicBrowserEntrypoint::class, fn (): ClassicBrowserEntrypoint => new ClassicBrowserEntrypoint(
            $coreRoot,
            $themeRoots,
            $publishedAssetsRoot
        ));
        $this->app->singleton(InstallAssetsCommand::class, fn ($app): InstallAssetsCommand => new InstallAssetsCommand(
            $app->make(Filesystem::class),
            $coreRoot,
            $app->make(ClassicBrowserBundles::class)
        ));
        $this->app->singleton(ClearCacheCommand::class, fn ($app): ClearCacheCommand => new ClearCacheCommand(
            $app->make(Filesystem::class),
            $app->make(FilesystemFactory::class),
            $coreRoot
        ));
    }

    public function boot(): void
    {
        $this->publishes(array(
            __DIR__ . '/../config/kcfinder.php' => config_path('kcfinder.php'),
        ), 'kcfinder-config');

        if ($this->app->runningInConsole()) {
            $this->commands(array(InstallAssetsCommand::class, ClearCacheCommand::class));
        }

        $config = $this->app->make(ConfigRepository::class);
        if ((bool) $config->get('kcfinder.http.enabled', false)) {
            $prefix = trim((string) $config->get('kcfinder.http.prefix', 'kcfinder'), '/');
            $middleware = (array) $config->get('kcfinder.http.middleware', array('web', 'auth'));
            Route::middleware($middleware)
                ->prefix($prefix)
                ->group(static function (): void {
                    Route::match(array('GET', 'POST'), '/{path?}', ClassicBrowserController::class)
                        ->where('path', '.*')
                        ->name('kcfinder.browser');
                });
        }
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

    private function coreRoot(): string
    {
        $file = (new ReflectionClass(\KCFinder\Domain\OperationContext::class))->getFileName();
        if (!is_string($file)) {
            throw new RuntimeException('Unable to locate the installed KCFinder core package.');
        }
        return dirname($file, 3);
    }
}
