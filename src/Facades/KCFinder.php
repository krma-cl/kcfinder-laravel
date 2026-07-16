<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \KCFinder\Domain\FileDescriptor select(string $logicalPath)
 * @method static string selectedUrl(string $logicalPath)
 * @method static string previewUrl(string $logicalPath)
 * @method static \Krma\KCFinder\Laravel\Domain\FileSnapshot snapshot(string $logicalPath, string $operation = 'select')
 * @method static \Krma\KCFinder\Laravel\Domain\OperationResult reportUploaded(string $logicalPath, array<int, \Krma\KCFinder\Laravel\Domain\OperationWarning> $warnings = array())
 * @method static \Krma\KCFinder\Laravel\Domain\OperationResult reportEdited(string $logicalPath, array<int, \Krma\KCFinder\Laravel\Domain\OperationWarning> $warnings = array())
 * @method static \Krma\KCFinder\Laravel\Domain\OperationResult reportMoved(\Krma\KCFinder\Laravel\Domain\FileSnapshot $previous, string $newLogicalPath, array<int, \Krma\KCFinder\Laravel\Domain\OperationWarning> $warnings = array())
 * @method static \Krma\KCFinder\Laravel\Domain\OperationResult reportRenamed(\Krma\KCFinder\Laravel\Domain\FileSnapshot $previous, string $newLogicalPath, array<int, \Krma\KCFinder\Laravel\Domain\OperationWarning> $warnings = array())
 * @method static \Krma\KCFinder\Laravel\Domain\OperationResult reportDeleted(\Krma\KCFinder\Laravel\Domain\FileSnapshot $deleted, array<int, \Krma\KCFinder\Laravel\Domain\OperationWarning> $warnings = array())
 * @method static \Krma\KCFinder\Laravel\Domain\OperationResult reportDirectoryCreated(string $logicalPath, array<int, \Krma\KCFinder\Laravel\Domain\OperationWarning> $warnings = array())
 */
final class KCFinder extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Krma\KCFinder\Laravel\KCFinderManager::class;
    }
}
