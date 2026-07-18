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
        $app['config']->set('kcfinder.http.prefix', 'dashboard/kcfinder');
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

    public function testNestedAuthenticatedRouteServesBundlesAndLocalizationSafely(): void
    {
        $expectations = array(
            '/dashboard/kcfinder/browser-assets/base.js' => 'text/javascript',
            '/dashboard/kcfinder/browser-assets/base.css' => 'text/css',
            '/dashboard/kcfinder/browser-assets/themes/default.js' => 'text/javascript',
            '/dashboard/kcfinder/browser-assets/themes/default.css' => 'text/css',
            '/dashboard/kcfinder/browser-assets/themes/bootstrap5.css' => 'text/css',
            '/dashboard/kcfinder/js_localize.php?lng=es' => 'text/javascript',
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
        self::assertFileExists($target . '/bundles/themes/bootstrap5.css');
        self::assertFileExists($target . '/css/Jcrop.gif');
        self::assertFileExists($target . '/themes/bootstrap5/img/bi/clipboard-plus.svg');
        self::assertFileDoesNotExist($target . '/js/index.php');
        self::assertFileDoesNotExist($target . '/css/index.php');

        $baseCss = file_get_contents($target . '/bundles/base.css');
        $bootstrapCss = file_get_contents($target . '/bundles/themes/bootstrap5.css');
        self::assertIsString($baseCss);
        self::assertIsString($bootstrapCss);
        self::assertStringContainsString('url(../css/Jcrop.gif)', $baseCss);
        self::assertStringContainsString(
            'url(../../themes/bootstrap5/img/bi/clipboard-plus.svg)',
            $bootstrapCss
        );

        $bundleFiles = array(
            $target . '/bundles/base.css',
            $target . '/bundles/themes/bootstrap5.css',
        );
        foreach ($bundleFiles as $bundleFile) {
            $content = file_get_contents($bundleFile);
            self::assertIsString($content);
            $relative = str_replace('\\', '/', substr($bundleFile, strlen($target) + 1));
            $bundleUrl = '/' . preg_replace('#^bundles/#', 'browser-assets/', $relative);
            $this->assertCssAssetsResolve($content, $bundleUrl, $target);
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFirstBrowserRequestWithoutExistingKcfinderCookiesReturnsHtml(): void
    {
        $_COOKIE = array();
        unset($_SERVER['HTTP_HOST'], $_SERVER['HTTPS']);

        $response = $this->get('/dashboard/kcfinder/browse.php');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Content-Security-Policy');
        $body = (string) $response->getContent();
        self::assertStringContainsString('<!DOCTYPE html>', $body);
        self::assertStringContainsString('src="browser-assets/base.js?v=', $body);
        self::assertStringContainsString('href="browser-assets/base.css?v=', $body);
        self::assertStringContainsString('src="browser-assets/themes/default.js?v=', $body);
        self::assertStringContainsString('href="browser-assets/themes/default.css?v=', $body);
        self::assertStringNotContainsString('src="js/index.php"', $body);
        self::assertStringNotContainsString('href="css/index.php"', $body);

        $base = strpos($body, 'src="browser-assets/base.js');
        $localization = strpos($body, 'src="js_localize.php');
        $theme = strpos($body, 'src="browser-assets/themes/default.js');
        self::assertIsInt($base);
        self::assertIsInt($localization);
        self::assertIsInt($theme);
        self::assertLessThan($localization, $base);
        self::assertLessThan($theme, $localization);
    }

    private function assertCssAssetsResolve(string $css, string $bundleUrl, string $target): void
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;
        preg_match_all(
            '#url\(\s*(?:(["\'])(.*?)\1|([^)]*?))\s*\)#is',
            $css,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $quoted = isset($match[1]) && $match[1] !== '';
            $url = trim((string) ($quoted ? ($match[2] ?? '') : ($match[3] ?? '')));
            if (
                $url === ''
                || str_starts_with($url, '/')
                || str_starts_with($url, '#')
                || str_starts_with($url, '?')
                || str_starts_with(strtolower($url), 'var(')
                || preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) === 1
            ) {
                continue;
            }

            $path = preg_replace('/[?#].*$/', '', $url) ?? $url;
            $slash = strrpos($bundleUrl, '/');
            $directory = $slash === false ? '/' : substr($bundleUrl, 0, $slash + 1);
            $resolved = $this->normalizeUrlPath($directory . $path);

            self::assertFileExists(
                $target . str_replace('/', DIRECTORY_SEPARATOR, $resolved),
                sprintf('CSS asset %s referenced by %s must be published.', $url, $bundleUrl)
            );
        }
    }

    private function normalizeUrlPath(string $path): string
    {
        $segments = array();
        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }
        return '/' . implode('/', $segments);
    }
}
