<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Tests;

use Illuminate\Contracts\Events\Dispatcher;
use KCFinder\Contract\AuthorizationInterface;
use KCFinder\Contract\FileMetadataProviderInterface;
use KCFinder\Domain\FileDescriptor;
use Krma\KCFinder\Laravel\Contracts\ActorResolverInterface;
use Krma\KCFinder\Laravel\Contracts\ChecksumProviderInterface;
use Krma\KCFinder\Laravel\Domain\FileSnapshot;
use Krma\KCFinder\Laravel\Events\FileMoved;
use Krma\KCFinder\Laravel\Events\FileUploaded;
use Krma\KCFinder\Laravel\KCFinderOperationReporter;
use PHPUnit\Framework\TestCase;

final class KCFinderOperationReporterTest extends TestCase
{
    public function testSnapshotRequiresAuthorizationForTheIntendedOperation(): void
    {
        $file = $this->file('/images/photo.jpg');
        $metadata = $this->createMock(FileMetadataProviderInterface::class);
        $metadata->expects(self::once())->method('metadata')->with('/images/photo.jpg')->willReturn($file);
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->expects(self::once())->method('can')->with('delete', '/images/photo.jpg')->willReturn(true);
        $checksums = $this->createMock(ChecksumProviderInterface::class);
        $checksums->method('checksum')->willReturn('abc123');

        $snapshot = (new KCFinderOperationReporter(
            $metadata,
            $authorization,
            $this->createMock(Dispatcher::class),
            $this->createMock(ActorResolverInterface::class),
            $checksums
        ))->snapshot('/images/photo.jpg', 'delete');

        self::assertSame('/images/photo.jpg', $snapshot->file->path);
    }

    public function testUploadReturnsStructuredJsonAndDispatchesACompleteEvent(): void
    {
        $file = $this->file('/images/photo.jpg');
        $metadata = $this->createMock(FileMetadataProviderInterface::class);
        $metadata->expects(self::once())->method('metadata')->with('/images/photo.jpg')->willReturn($file);
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->expects(self::once())->method('can')->with('upload', '/images/photo.jpg')->willReturn(true);
        $events = $this->createMock(Dispatcher::class);
        $events->expects(self::once())->method('dispatch')->with(self::callback(
            static fn (mixed $event): bool => $event instanceof FileUploaded
                && $event->file->file === $file
                && $event->file->checksum === 'abc123'
                && $event->user === 42
        ));
        $actor = $this->createMock(ActorResolverInterface::class);
        $actor->method('resolve')->willReturn(42);
        $checksums = $this->createMock(ChecksumProviderInterface::class);
        $checksums->expects(self::once())->method('checksum')->with('/images/photo.jpg')->willReturn('abc123');

        $result = (new KCFinderOperationReporter($metadata, $authorization, $events, $actor, $checksums))
            ->uploaded('/images/photo.jpg');

        self::assertTrue($result->success);
        self::assertSame('upload', $result->operation);
        self::assertSame(array($file->toArray()), $result->toArray()['files']);
    }

    public function testMoveCarriesPreviousAndCurrentSnapshotsWithoutRescanningTheLibrary(): void
    {
        $previousFile = $this->file('/images/old.jpg');
        $currentFile = $this->file('/archive/new.jpg');
        $previous = new FileSnapshot($previousFile, 'old-checksum');
        $metadata = $this->createMock(FileMetadataProviderInterface::class);
        $metadata->expects(self::once())->method('metadata')->with('/archive/new.jpg')->willReturn($currentFile);
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->expects(self::exactly(2))->method('can')->willReturnMap(array(
            array('move', '/images/old.jpg', true),
            array('move', '/archive/new.jpg', true),
        ));
        $events = $this->createMock(Dispatcher::class);
        $events->expects(self::once())->method('dispatch')->with(self::callback(
            static fn (mixed $event): bool => $event instanceof FileMoved
                && $event->previous === $previous
                && $event->file->file === $currentFile
                && $event->user === 'user-7'
        ));
        $actor = $this->createMock(ActorResolverInterface::class);
        $actor->method('resolve')->willReturn('user-7');
        $checksums = $this->createMock(ChecksumProviderInterface::class);
        $checksums->expects(self::once())->method('checksum')->with('/archive/new.jpg')->willReturn('new-checksum');

        $result = (new KCFinderOperationReporter($metadata, $authorization, $events, $actor, $checksums))
            ->moved($previous, '/archive/new.jpg');

        self::assertSame('move', $result->operation);
        self::assertSame('/archive/new.jpg', $result->files[0]->path);
    }

    private function file(string $path): FileDescriptor
    {
        return new FileDescriptor(
            basename($path),
            $path,
            '/storage' . $path,
            'image/jpeg',
            1024
        );
    }
}
