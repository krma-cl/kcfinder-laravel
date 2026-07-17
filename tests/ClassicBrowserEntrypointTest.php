<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Tests;

use Krma\KCFinder\Laravel\Http\ClassicBrowserEntrypoint;
use PHPUnit\Framework\TestCase;

final class ClassicBrowserEntrypointTest extends TestCase
{
    public function testItServesAStaticAssetFromAnExternalThemeRoot(): void
    {
        $root = sys_get_temp_dir() . '/kcfinder-entrypoint-' . bin2hex(random_bytes(4));
        $theme = $root . '-theme';
        mkdir($root, 0777, true);
        mkdir($theme . '/img', 0777, true);
        file_put_contents($theme . '/img/icon.svg', '<svg id="external-theme"/>');

        $response = (new ClassicBrowserEntrypoint(
            $root,
            array('bootstrap5' => $theme)
        ))->run('themes/bootstrap5/img/icon.svg');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<svg id="external-theme"/>', $response->getContent());
        self::assertSame('image/svg+xml', $response->headers->get('Content-Type'));
    }
}
