<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Events;

use KCFinder\Domain\FileDescriptor;

final readonly class FileSelected
{
    public function __construct(public FileDescriptor $file)
    {
    }
}
