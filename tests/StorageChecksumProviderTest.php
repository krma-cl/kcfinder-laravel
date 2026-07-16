<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Tests;

use Illuminate\Filesystem\FilesystemAdapter;
use Krma\KCFinder\Laravel\StorageChecksumProvider;
use PHPUnit\Framework\TestCase;

final class StorageChecksumProviderTest extends TestCase
{
    public function testItStreamsTheConfiguredChecksumWithoutLoadingTheWholeFile(): void
    {
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);
        fwrite($stream, 'KCFinder');
        rewind($stream);

        $disk = $this->createMock(FilesystemAdapter::class);
        $disk->expects(self::once())->method('readStream')->with('docs/report.pdf')->willReturn($stream);

        self::assertSame(
            hash('sha256', 'KCFinder'),
            (new StorageChecksumProvider($disk))->checksum('/docs/report.pdf')
        );
    }

    public function testChecksumsCanBeDisabled(): void
    {
        $disk = $this->createMock(FilesystemAdapter::class);
        $disk->expects(self::never())->method('readStream');

        self::assertNull((new StorageChecksumProvider($disk, null))->checksum('/docs/report.pdf'));
    }
}
