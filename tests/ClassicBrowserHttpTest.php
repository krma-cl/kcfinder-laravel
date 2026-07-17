<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Tests;

use Illuminate\Contracts\Auth\Access\Gate;
use Krma\KCFinder\Laravel\KCFinderServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

final class ClassicBrowserHttpTest extends TestCase
{
    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return array(KCFinderServiceProvider::class);
    }

    protected function defineEnvironment($app): void
    {
        $root = sys_get_temp_dir() . '/kcfinder-http-tests';
        if (!is_dir($root)) {
            mkdir($root, 0777, true);
        }
        $app['config']->set('filesystems.disks.kcfinder-http', array(
            'driver' => 'local',
            'root' => $root,
        ));
        $app['config']->set('kcfinder.disk', 'kcfinder-http');
        $app['config']->set('kcfinder.http.enabled', true);
        $app['config']->set('kcfinder.http.middleware', array());
        $app['config']->set(
            'kcfinder.http.assets_path',
            sys_get_temp_dir() . '/kcfinder-published-assets'
        );
        $app['config']->set(
            'kcfinder.http.session.save_path',
            sys_get_temp_dir() . '/kcfinder-http-sessions'
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(Gate::class)->define(
            'kcfinder.select',
            static fn ($user = null, string $operation = '', string $path = ''): bool => true
        );
    }

    public function testAuthenticatedRouteServesAllFourLegacyBundleUrlsSafely(): void
    {
        $expectations = array(
            '/kcfinder/js/index.php' => 'text/javascript',
            '/kcfinder/css/index.php' => 'text/css',
            '/kcfinder/themes/default/js.php' => 'text/javascript',
            '/kcfinder/themes/default/css.php' => 'text/css',
        );

        foreach ($expectations as $url => $contentType) {
            $response = $this->get($url);
            $response->assertOk();
            $response->assertHeader('Content-Type', $contentType . '; charset=UTF-8');
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $response->assertHeader(
                'Content-Security-Policy',
                "default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'; frame-ancestors 'self'"
            );
            self::assertNotSame('', $response->getContent());
        }
    }

    public function testInstallAssetsPublishesStaticBundlesWithoutPhpEntrypoints(): void
    {
        $target = (string) config('kcfinder.http.assets_path');
        if (is_dir($target)) {
            $this->app->make('files')->deleteDirectory($target);
        }

        $this->artisan('kcfinder:install-assets', array('--force' => true))
            ->assertSuccessful();

        self::assertFileExists($target . '/bundles/base.js');
        self::assertFileExists($target . '/bundles/base.css');
        self::assertFileExists($target . '/bundles/themes/default.js');
        self::assertFileExists($target . '/bundles/themes/default.css');
        self::assertFileDoesNotExist($target . '/js/index.php');
        self::assertFileDoesNotExist($target . '/css/index.php');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFirstBrowserRequestWithoutExistingKcfinderCookiesReturnsHtml(): void
    {
        $_COOKIE = array();
        unset($_SERVER['HTTP_HOST'], $_SERVER['HTTPS']);

        $response = $this->get('/kcfinder/browse.php');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Content-Security-Policy');
        self::assertStringContainsString('<!DOCTYPE html>', (string) $response->getContent());
    }
}
