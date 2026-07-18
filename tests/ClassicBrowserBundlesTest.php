<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Tests;

use Krma\KCFinder\Laravel\Http\ClassicBrowserBundles;
use PHPUnit\Framework\TestCase;

final class ClassicBrowserBundlesTest extends TestCase
{
    public function testItRebasesOnlyRelativeCssAssetUrlsForVirtualBundles(): void
    {
        $root = sys_get_temp_dir() . '/kcfinder-css-bundles-' . bin2hex(random_bytes(4));
        $theme = $root . '-theme';
        mkdir($root . '/css', 0777, true);
        mkdir($theme . '/img/bi', 0777, true);
        file_put_contents($root . '/css/Jcrop.gif', 'gif');
        file_put_contents($theme . '/img/bi/clipboard-plus.svg', '<svg/>');
        file_put_contents(
            $root . '/css/001.css',
            '.crop{background:url(Jcrop.gif)}'
            . '.data{background:url("data:image/gif;base64,AAAA")}'
            . '.absolute{background:url(/assets/icon.svg)}'
            . '.external{background:url(https://example.test/icon.svg)}'
            . '.protocol{background:url(//cdn.example.test/icon.svg)}'
            . '.blob{background:url(blob:example)}'
            . '.fragment{filter:url(#mask)}'
            . '.variable{background:url(var(--asset))}'
            . '/* url(comment.png) */'
        );
        file_put_contents(
            $theme . '/01.css',
            '.copy{mask:url(img/bi/clipboard-plus.svg)}'
        );

        $bundles = new ClassicBrowserBundles($root, array('bootstrap5' => $theme));
        $base = $bundles->render('browser-assets/base.css');
        $bootstrap = $bundles->render('browser-assets/themes/bootstrap5.css');

        self::assertNotNull($base);
        self::assertStringContainsString('url(../css/Jcrop.gif)', $base['content']);
        self::assertStringContainsString('url("data:image/gif;base64,AAAA")', $base['content']);
        self::assertStringContainsString('url(/assets/icon.svg)', $base['content']);
        self::assertStringContainsString('url(https://example.test/icon.svg)', $base['content']);
        self::assertStringContainsString('url(//cdn.example.test/icon.svg)', $base['content']);
        self::assertStringContainsString('url(blob:example)', $base['content']);
        self::assertStringContainsString('url(#mask)', $base['content']);
        self::assertStringContainsString('url(var(--asset))', $base['content']);
        self::assertStringContainsString('/* url(comment.png) */', $base['content']);

        self::assertNotNull($bootstrap);
        self::assertStringContainsString(
            'url(../../themes/bootstrap5/img/bi/clipboard-plus.svg)',
            $bootstrap['content']
        );
    }

    public function testItRebasesPreviouslyPublishedCssWithoutDuplicatingThePrefix(): void
    {
        $root = sys_get_temp_dir() . '/kcfinder-published-css-source-' . bin2hex(random_bytes(4));
        $published = sys_get_temp_dir() . '/kcfinder-published-css-target-' . bin2hex(random_bytes(4));
        mkdir($root . '/css', 0777, true);
        mkdir($published . '/bundles', 0777, true);
        file_put_contents($root . '/css/001.css', '.source{}');
        file_put_contents(
            $published . '/bundles/base.css',
            '.old{background:url(Jcrop.gif)}.new{background:url(../css/Jcrop.gif)}'
        );

        $bundle = (new ClassicBrowserBundles($root))
            ->render('browser-assets/base.css', $published);

        self::assertNotNull($bundle);
        self::assertSame(2, substr_count($bundle['content'], 'url(../css/Jcrop.gif)'));
        self::assertStringNotContainsString('../css/../css/', $bundle['content']);
    }
}
