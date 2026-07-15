<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/** @method static \KCFinder\Domain\FileDescriptor select(string $logicalPath) */
final class KCFinder extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Krma\KCFinder\Laravel\KCFinderManager::class;
    }
}
