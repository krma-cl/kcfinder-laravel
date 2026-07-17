<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Tests;

use Illuminate\Http\Request;
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

    public function testItBuildsLegacyBundleRoutesWithoutExecutingTheirPhpFiles(): void
    {
        $root = sys_get_temp_dir() . '/kcfinder-bundles-' . bin2hex(random_bytes(4));
        mkdir($root . '/js', 0777, true);
        mkdir($root . '/css', 0777, true);
        mkdir($root . '/themes/default', 0777, true);
        file_put_contents($root . '/js/001.js', 'window.first = true;');
        file_put_contents($root . '/js/002.js', 'window.second = true;');
        file_put_contents($root . '/js/index.php', '<?php die("must not run");');
        file_put_contents($root . '/css/001.css', 'body{display:block}');
        file_put_contents($root . '/css/index.php', '<?php die("must not run");');
        file_put_contents($root . '/themes/default/init.js', 'window.theme = true;');
        file_put_contents($root . '/themes/default/01.css', '.theme{color:black}');
        file_put_contents($root . '/themes/default/js.php', '<?php die("must not run");');
        file_put_contents($root . '/themes/default/css.php', '<?php die("must not run");');

        $entrypoint = new ClassicBrowserEntrypoint($root);
        $javascript = $entrypoint->run('js/index.php');
        $stylesheet = $entrypoint->run('css/index.php');
        $themeJavascript = $entrypoint->run('themes/default/js.php');
        $themeStylesheet = $entrypoint->run('themes/default/css.php');

        self::assertSame('window.first = true;window.second = true;', $javascript->getContent());
        self::assertSame('text/javascript; charset=UTF-8', $javascript->headers->get('Content-Type'));
        self::assertSame('body{display:block}', $stylesheet->getContent());
        self::assertSame('text/css; charset=UTF-8', $stylesheet->headers->get('Content-Type'));
        self::assertSame('window.theme = true;', $themeJavascript->getContent());
        self::assertSame('.theme{color:black}', $themeStylesheet->getContent());
    }

    public function testItTemporarilyProvidesAndRestoresServerVariablesForPhpEntrypoints(): void
    {
        $root = sys_get_temp_dir() . '/kcfinder-server-' . bin2hex(random_bytes(4));
        mkdir($root, 0777, true);
        file_put_contents(
            $root . '/browse.php',
            '<?php echo json_encode(array('
            . '"script" => $_SERVER["SCRIPT_FILENAME"], '
            . '"host" => $_SERVER["HTTP_HOST"], '
            . '"https" => $_SERVER["HTTPS"]));'
        );

        $_SERVER['SCRIPT_FILENAME'] = 'original-script.php';
        unset($_SERVER['HTTP_HOST'], $_SERVER['HTTPS']);
        $request = Request::create('https://example.test:8443/kcfinder/browse.php');

        $response = (new ClassicBrowserEntrypoint($root))->run('browse.php', $request);
        $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(realpath($root . '/browse.php'), $body['script']);
        self::assertSame('example.test:8443', $body['host']);
        self::assertSame('on', $body['https']);
        self::assertSame('original-script.php', $_SERVER['SCRIPT_FILENAME']);
        self::assertArrayNotHasKey('HTTP_HOST', $_SERVER);
        self::assertArrayNotHasKey('HTTPS', $_SERVER);
    }

    public function testItPrefersAStaticPublishedBundleWhenAvailable(): void
    {
        $root = sys_get_temp_dir() . '/kcfinder-bundle-source-' . bin2hex(random_bytes(4));
        $published = sys_get_temp_dir() . '/kcfinder-bundle-published-' . bin2hex(random_bytes(4));
        mkdir($root . '/js', 0777, true);
        mkdir($published . '/bundles', 0777, true);
        file_put_contents($root . '/js/001.js', 'window.source = true;');
        file_put_contents($root . '/js/index.php', '<?php die("must not run");');
        file_put_contents($published . '/bundles/base.js', 'window.published = true;');

        $response = (new ClassicBrowserEntrypoint($root, array(), $published))
            ->run('js/index.php');

        self::assertSame('window.published = true;', $response->getContent());
    }
}
