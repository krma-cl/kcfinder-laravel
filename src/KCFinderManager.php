<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel;

use Illuminate\Contracts\Events\Dispatcher;
use KCFinder\Application\FileSelectionService;
use KCFinder\Contract\AuthorizationInterface;
use KCFinder\Domain\FileDescriptor;
use KCFinder\Domain\LogicalPath;
use KCFinder\Exception\AuthorizationDenied;
use Krma\KCFinder\Laravel\Contracts\PreviewUrlResolverInterface;
use Krma\KCFinder\Laravel\Domain\FileSnapshot;
use Krma\KCFinder\Laravel\Domain\OperationResult;
use Krma\KCFinder\Laravel\Domain\OperationWarning;
use Krma\KCFinder\Laravel\Events\FileSelected;
use LogicException;

final class KCFinderManager
{
    public function __construct(
        private readonly FileSelectionService $selector,
        private readonly Dispatcher $events,
        private readonly ?KCFinderOperationReporter $operations = null,
        private readonly ?AuthorizationInterface $authorization = null,
        private readonly ?PreviewUrlResolverInterface $previewUrls = null
    ) {
    }

    public function select(string $logicalPath): FileDescriptor
    {
        $file = $this->selector->select($logicalPath);
        $this->events->dispatch(new FileSelected($file));
        return $file;
    }

    public function selectedUrl(string $logicalPath): string
    {
        return $this->select($logicalPath)->url;
    }

    public function previewUrl(string $logicalPath): string
    {
        $logicalPath = LogicalPath::fromString($logicalPath)->value();
        if ($this->authorization === null || $this->previewUrls === null) {
            throw new LogicException('Preview URL resolution is unavailable outside the Laravel service provider.');
        }
        if (!$this->authorization->can('preview', $logicalPath)) {
            throw new AuthorizationDenied();
        }
        return $this->previewUrls->resolve($logicalPath);
    }

    public function snapshot(string $logicalPath, string $operation = 'select'): FileSnapshot
    {
        return $this->operationReporter()->snapshot($logicalPath, $operation);
    }

    /** @param array<int, OperationWarning> $warnings */
    public function reportUploaded(string $logicalPath, array $warnings = array()): OperationResult
    {
        return $this->operationReporter()->uploaded($logicalPath, $warnings);
    }

    /** @param array<int, OperationWarning> $warnings */
    public function reportEdited(string $logicalPath, array $warnings = array()): OperationResult
    {
        return $this->operationReporter()->edited($logicalPath, $warnings);
    }

    /** @param array<int, OperationWarning> $warnings */
    public function reportMoved(FileSnapshot $previous, string $newLogicalPath, array $warnings = array()): OperationResult
    {
        return $this->operationReporter()->moved($previous, $newLogicalPath, $warnings);
    }

    /** @param array<int, OperationWarning> $warnings */
    public function reportCopied(FileSnapshot $previous, string $newLogicalPath, array $warnings = array()): OperationResult
    {
        return $this->operationReporter()->copied($previous, $newLogicalPath, $warnings);
    }

    /** @param array<int, OperationWarning> $warnings */
    public function reportRenamed(FileSnapshot $previous, string $newLogicalPath, array $warnings = array()): OperationResult
    {
        return $this->operationReporter()->renamed($previous, $newLogicalPath, $warnings);
    }

    /** @param array<int, OperationWarning> $warnings */
    public function reportDeleted(FileSnapshot $deleted, array $warnings = array()): OperationResult
    {
        return $this->operationReporter()->deleted($deleted, $warnings);
    }

    /** @param array<int, OperationWarning> $warnings */
    public function reportDirectoryCreated(string $logicalPath, array $warnings = array()): OperationResult
    {
        return $this->operationReporter()->directoryCreated($logicalPath, $warnings);
    }

    /** @param array<int, OperationWarning> $warnings */
    public function reportDirectoryRenamed(
        string $previousLogicalPath,
        string $newLogicalPath,
        array $warnings = array()
    ): OperationResult {
        return $this->operationReporter()->directoryRenamed($previousLogicalPath, $newLogicalPath, $warnings);
    }

    /** @param array<int, OperationWarning> $warnings */
    public function reportDirectoryDeleted(string $logicalPath, array $warnings = array()): OperationResult
    {
        return $this->operationReporter()->directoryDeleted($logicalPath, $warnings);
    }

    private function operationReporter(): KCFinderOperationReporter
    {
        if ($this->operations === null) {
            throw new LogicException('Operation reporting is unavailable outside the Laravel service provider.');
        }
        return $this->operations;
    }
}
