<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel;

use Illuminate\Contracts\Events\Dispatcher;
use KCFinder\Contract\AuthorizationInterface;
use KCFinder\Contract\FileMetadataProviderInterface;
use KCFinder\Domain\LogicalPath;
use KCFinder\Exception\AuthorizationDenied;
use Krma\KCFinder\Laravel\Contracts\ActorResolverInterface;
use Krma\KCFinder\Laravel\Contracts\ChecksumProviderInterface;
use Krma\KCFinder\Laravel\Domain\FileSnapshot;
use Krma\KCFinder\Laravel\Domain\OperationResult;
use Krma\KCFinder\Laravel\Domain\OperationWarning;
use Krma\KCFinder\Laravel\Events\DirectoryCreated;
use Krma\KCFinder\Laravel\Events\DirectoryDeleted;
use Krma\KCFinder\Laravel\Events\DirectoryRenamed;
use Krma\KCFinder\Laravel\Events\FileCopied;
use Krma\KCFinder\Laravel\Events\FileDeleted;
use Krma\KCFinder\Laravel\Events\FileEdited;
use Krma\KCFinder\Laravel\Events\FileMoved;
use Krma\KCFinder\Laravel\Events\FileRenamed;
use Krma\KCFinder\Laravel\Events\FileUploaded;

final class KCFinderOperationReporter
{
    public function __construct(
        private readonly FileMetadataProviderInterface $metadata,
        private readonly AuthorizationInterface $authorization,
        private readonly Dispatcher $events,
        private readonly ActorResolverInterface $actor,
        private readonly ChecksumProviderInterface $checksums
    ) {
    }

    public function snapshot(string $logicalPath, string $operation = 'select'): FileSnapshot
    {
        $this->authorize($operation, $logicalPath);
        return $this->snapshotFile($logicalPath);
    }

    private function snapshotFile(string $logicalPath): FileSnapshot
    {
        $file = $this->metadata->metadata($logicalPath);
        return new FileSnapshot($file, $this->checksums->checksum($file->path));
    }

    /** @param array<int, OperationWarning> $warnings */
    public function uploaded(string $logicalPath, array $warnings = array()): OperationResult
    {
        $this->authorize('upload', $logicalPath);
        $snapshot = $this->snapshotFile($logicalPath);
        $this->events->dispatch(new FileUploaded($snapshot, $this->actor->resolve()));
        return OperationResult::success('upload', array($snapshot->file), $warnings);
    }

    /** @param array<int, OperationWarning> $warnings */
    public function edited(string $logicalPath, array $warnings = array()): OperationResult
    {
        $this->authorize('edit', $logicalPath);
        $snapshot = $this->snapshotFile($logicalPath);
        $this->events->dispatch(new FileEdited($snapshot, $this->actor->resolve()));
        return OperationResult::success('edit', array($snapshot->file), $warnings);
    }

    /** @param array<int, OperationWarning> $warnings */
    public function moved(FileSnapshot $previous, string $newLogicalPath, array $warnings = array()): OperationResult
    {
        return $this->relocated('move', $previous, $newLogicalPath, $warnings);
    }

    /** @param array<int, OperationWarning> $warnings */
    public function copied(FileSnapshot $previous, string $newLogicalPath, array $warnings = array()): OperationResult
    {
        return $this->relocated('copy', $previous, $newLogicalPath, $warnings);
    }

    /** @param array<int, OperationWarning> $warnings */
    public function renamed(FileSnapshot $previous, string $newLogicalPath, array $warnings = array()): OperationResult
    {
        return $this->relocated('rename', $previous, $newLogicalPath, $warnings);
    }

    /** @param array<int, OperationWarning> $warnings */
    public function deleted(FileSnapshot $deleted, array $warnings = array()): OperationResult
    {
        $this->authorize('delete', $deleted->file->path);
        $this->events->dispatch(new FileDeleted($deleted, $this->actor->resolve()));
        return OperationResult::success('delete', array($deleted->file), $warnings);
    }

    /** @param array<int, OperationWarning> $warnings */
    public function directoryCreated(string $logicalPath, array $warnings = array()): OperationResult
    {
        $logicalPath = LogicalPath::fromString($logicalPath)->value();
        $this->authorize('create_directory', $logicalPath);
        $this->events->dispatch(new DirectoryCreated($logicalPath, $this->actor->resolve()));
        return OperationResult::success('create_directory', array(), $warnings);
    }

    /** @param array<int, OperationWarning> $warnings */
    public function directoryRenamed(
        string $previousLogicalPath,
        string $newLogicalPath,
        array $warnings = array()
    ): OperationResult {
        $previousLogicalPath = LogicalPath::fromString($previousLogicalPath)->value();
        $newLogicalPath = LogicalPath::fromString($newLogicalPath)->value();
        $this->authorize('rename', $previousLogicalPath);
        $this->authorize('rename', $newLogicalPath);
        $this->events->dispatch(new DirectoryRenamed(
            $previousLogicalPath,
            $newLogicalPath,
            $this->actor->resolve()
        ));
        return OperationResult::success('rename_directory', array(), $warnings);
    }

    /** @param array<int, OperationWarning> $warnings */
    public function directoryDeleted(string $logicalPath, array $warnings = array()): OperationResult
    {
        $logicalPath = LogicalPath::fromString($logicalPath)->value();
        $this->authorize('delete', $logicalPath);
        $this->events->dispatch(new DirectoryDeleted($logicalPath, $this->actor->resolve()));
        return OperationResult::success('delete_directory', array(), $warnings);
    }

    /** @param array<int, OperationWarning> $warnings */
    private function relocated(
        string $operation,
        FileSnapshot $previous,
        string $newLogicalPath,
        array $warnings
    ): OperationResult {
        $this->authorize($operation, $previous->file->path);
        $this->authorize($operation, $newLogicalPath);
        $current = $this->snapshotFile($newLogicalPath);
        $event = match ($operation) {
            'copy' => new FileCopied($previous, $current, $this->actor->resolve()),
            'move' => new FileMoved($previous, $current, $this->actor->resolve()),
            default => new FileRenamed($previous, $current, $this->actor->resolve()),
        };
        $this->events->dispatch($event);
        return OperationResult::success($operation, array($current->file), $warnings);
    }

    private function authorize(string $operation, string $logicalPath): void
    {
        $logicalPath = LogicalPath::fromString($logicalPath)->value();
        if (!$this->authorization->can($operation, $logicalPath)) {
            throw new AuthorizationDenied();
        }
    }
}
