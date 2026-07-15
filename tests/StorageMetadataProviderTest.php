<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Tests;

use Illuminate\Filesystem\FilesystemAdapter;
use Krma\KCFinder\Laravel\StorageMetadataProvider;
use Krma\KCFinder\Laravel\StorageUrlResolver;
use PHPUnit\Framework\TestCase;

final class StorageMetadataProviderTest extends TestCase
{
    public function testItBuildsTheStableSelectorPayload(): void
    {
        $disk = $this->createMock(FilesystemAdapter::class);
        $disk->expects(self::once())->method('exists')->with('docs/report.pdf')->willReturn(true);
        $disk->expects(self::once())->method('size')->with('docs/report.pdf')->willReturn(184320);
        $disk->expects(self::once())->method('mimeType')->with('docs/report.pdf')->willReturn('application/pdf');

        $provider = new StorageMetadataProvider($disk, new StorageUrlResolver($disk, '/storage/transparencia'));
        self::assertSame(array(
            'name' => 'report.pdf',
            'path' => '/docs/report.pdf',
            'url' => '/storage/transparencia/docs/report.pdf',
            'mime' => 'application/pdf',
            'size' => 184320,
        ), $provider->metadata('/docs/report.pdf')->toArray());
    }
}
