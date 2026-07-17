<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Tests;

use Illuminate\Contracts\Events\Dispatcher;
use KCFinder\Contract\AuthorizationInterface;
use KCFinder\Contract\FileMetadataProviderInterface;
use KCFinder\Domain\FileDescriptor;
use KCFinder\Domain\OperationContext;
use Krma\KCFinder\Laravel\ClassicBrowserBridge;
use Krma\KCFinder\Laravel\Contracts\ActorResolverInterface;
use Krma\KCFinder\Laravel\Contracts\ChecksumProviderInterface;
use Krma\KCFinder\Laravel\Events\DirectoryCreated;
use Krma\KCFinder\Laravel\Events\DirectoryDeleted;
use Krma\KCFinder\Laravel\Events\DirectoryRenamed;
use Krma\KCFinder\Laravel\Events\FileCopied;
use Krma\KCFinder\Laravel\Events\FileRenamed;
use Krma\KCFinder\Laravel\Events\FileUploaded;
use Krma\KCFinder\Laravel\KCFinderOperationReporter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ClassicBrowserBridgeTest extends TestCase
{
    public function testItTranslatesAClassicUploadIntoTheNativeLaravelEvent(): void
    {
        $file = $this->file('/images/photo.jpg');
        $metadata = $this->createMock(FileMetadataProviderInterface::class);
        $metadata->expects(self::once())->method('metadata')->with('/images/photo.jpg')->willReturn($file);
        $authorization = $this->allowing(array(array('upload', '/images/photo.jpg', true)));
        $events = $this->createMock(Dispatcher::class);
        $events->expects(self::once())->method('dispatch')->with(self::callback(
            static fn (mixed $event): bool => $event instanceof FileUploaded
                && $event->file->file === $file
                && $event->user === 'user-42'
        ));
        $bridge = new ClassicBrowserBridge($this->reporter($metadata, $authorization, $events));
        $operation = new OperationContext('upload', '/images/photo.jpg');

        self::assertNull($bridge->before($operation));
        $bridge->succeeded($operation);
    }

    public function testItCarriesTheAuthorizedSnapshotAcrossAClassicRename(): void
    {
        $old = $this->file('/images/old.jpg');
        $new = $this->file('/images/new.jpg');
        $metadata = $this->createMock(FileMetadataProviderInterface::class);
        $metadata->expects(self::exactly(2))->method('metadata')->willReturnMap(array(
            array('/images/old.jpg', $old),
            array('/images/new.jpg', $new),
        ));
        $authorization = $this->allowing(array(
            array('rename', '/images/old.jpg', true),
            array('rename', '/images/old.jpg', true),
            array('rename', '/images/new.jpg', true),
        ));
        $events = $this->createMock(Dispatcher::class);
        $events->expects(self::once())->method('dispatch')->with(self::callback(
            static fn (mixed $event): bool => $event instanceof FileRenamed
                && $event->previous->file === $old
                && $event->file->file === $new
                && $event->previous->checksum === 'checksum:/images/old.jpg'
        ));
        $bridge = new ClassicBrowserBridge($this->reporter($metadata, $authorization, $events));
        $operation = new OperationContext('rename', '/images/old.jpg', '/images/new.jpg');

        $snapshot = $bridge->before($operation);
        $bridge->succeeded($operation, $snapshot);
    }

    public function testItTranslatesClassicDirectoryCreation(): void
    {
        $metadata = $this->createMock(FileMetadataProviderInterface::class);
        $authorization = $this->allowing(array(array('create_directory', '/archive', true)));
        $events = $this->createMock(Dispatcher::class);
        $events->expects(self::once())->method('dispatch')->with(self::callback(
            static fn (mixed $event): bool => $event instanceof DirectoryCreated
                && $event->path === '/archive'
                && $event->user === 'user-42'
        ));
        $bridge = new ClassicBrowserBridge($this->reporter($metadata, $authorization, $events));
        $operation = new OperationContext(
            'create_directory',
            '/archive',
            null,
            OperationContext::RESOURCE_DIRECTORY
        );

        $bridge->succeeded($operation);
    }

    public function testItTranslatesFileCopyWithBothSnapshots(): void
    {
        $old = $this->file('/images/original.jpg');
        $new = $this->file('/archive/original.jpg');
        $metadata = $this->createMock(FileMetadataProviderInterface::class);
        $metadata->expects(self::exactly(2))->method('metadata')->willReturnMap(array(
            array('/images/original.jpg', $old),
            array('/archive/original.jpg', $new),
        ));
        $authorization = $this->allowing(array(
            array('copy', '/images/original.jpg', true),
            array('copy', '/images/original.jpg', true),
            array('copy', '/archive/original.jpg', true),
        ));
        $events = $this->createMock(Dispatcher::class);
        $events->expects(self::once())->method('dispatch')->with(self::callback(
            static fn (mixed $event): bool => $event instanceof FileCopied
                && $event->previous->file === $old
                && $event->file->file === $new
        ));
        $bridge = new ClassicBrowserBridge($this->reporter($metadata, $authorization, $events));
        $operation = new OperationContext('copy', '/images/original.jpg', '/archive/original.jpg');

        $bridge->succeeded($operation, $bridge->before($operation));
    }

    public function testItTranslatesDirectoryRenameAndDelete(): void
    {
        $metadata = $this->createMock(FileMetadataProviderInterface::class);
        $authorization = $this->allowing(array(
            array('rename', '/old', true),
            array('rename', '/new', true),
            array('delete', '/new', true),
        ));
        $events = $this->createMock(Dispatcher::class);
        $events->expects(self::exactly(2))->method('dispatch')->with(self::callback(
            static fn (mixed $event): bool => (
                $event instanceof DirectoryRenamed
                && $event->previousPath === '/old'
                && $event->path === '/new'
            ) || (
                $event instanceof DirectoryDeleted
                && $event->path === '/new'
            )
        ));
        $bridge = new ClassicBrowserBridge($this->reporter($metadata, $authorization, $events));

        $bridge->succeeded(new OperationContext(
            'rename',
            '/old',
            '/new',
            OperationContext::RESOURCE_DIRECTORY
        ));
        $bridge->succeeded(new OperationContext(
            'delete',
            '/new',
            null,
            OperationContext::RESOURCE_DIRECTORY
        ));
    }

    /** @param array<int, array{0: string, 1: string, 2: bool}> $calls */
    private function allowing(array $calls): AuthorizationInterface&MockObject
    {
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->expects(self::exactly(count($calls)))->method('can')->willReturnMap($calls);
        return $authorization;
    }

    private function reporter(
        FileMetadataProviderInterface $metadata,
        AuthorizationInterface $authorization,
        Dispatcher $events
    ): KCFinderOperationReporter {
        $actor = $this->createMock(ActorResolverInterface::class);
        $actor->method('resolve')->willReturn('user-42');
        $checksums = $this->createMock(ChecksumProviderInterface::class);
        $checksums->method('checksum')->willReturnCallback(
            static fn (string $path): string => 'checksum:' . $path
        );
        return new KCFinderOperationReporter($metadata, $authorization, $events, $actor, $checksums);
    }

    private function file(string $path): FileDescriptor
    {
        return new FileDescriptor(basename($path), $path, '/storage' . $path, 'image/jpeg', 1024);
    }
}
