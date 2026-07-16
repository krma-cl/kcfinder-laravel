<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Tests;

use KCFinder\Contract\UrlResolverInterface;
use Krma\KCFinder\Laravel\Contracts\PreviewUrlResolverInterface;
use Krma\KCFinder\Laravel\Contracts\SelectedUrlResolverInterface;
use Krma\KCFinder\Laravel\KCFinderManager;
use Krma\KCFinder\Laravel\KCFinderOperationReporter;
use Krma\KCFinder\Laravel\KCFinderServiceProvider;
use Orchestra\Testbench\TestCase;

final class KCFinderServiceProviderTest extends TestCase
{
    public function testSelectedResolverContractExtendsTheCoreContract(): void
    {
        self::assertTrue(is_subclass_of(SelectedUrlResolverInterface::class, UrlResolverInterface::class));
    }

    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return array(KCFinderServiceProvider::class);
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('filesystems.disks.kcfinder-test', array(
            'driver' => 'local',
            'root' => sys_get_temp_dir() . '/kcfinder-laravel-tests',
        ));
        $app['config']->set('kcfinder.disk', 'kcfinder-test');
        $app['config']->set('kcfinder.urls.selected.prefix', '/storage');
        $app['config']->set('kcfinder.urls.preview.prefix', '/internal/preview');
    }

    public function testItRegistersSeparateUrlResolversAndOperationServices(): void
    {
        $selected = $this->app->make(SelectedUrlResolverInterface::class);
        $legacy = $this->app->make(UrlResolverInterface::class);
        $preview = $this->app->make(PreviewUrlResolverInterface::class);

        self::assertSame($selected, $legacy);
        self::assertSame('/storage/docs/report.pdf', $selected->resolve('/docs/report.pdf'));
        self::assertSame('/internal/preview/docs/report.pdf', $preview->resolve('/docs/report.pdf'));
        self::assertInstanceOf(KCFinderOperationReporter::class, $this->app->make(KCFinderOperationReporter::class));
        self::assertInstanceOf(KCFinderManager::class, $this->app->make(KCFinderManager::class));
    }
}
