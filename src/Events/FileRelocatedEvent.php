<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Events;

use Krma\KCFinder\Laravel\Domain\FileSnapshot;

abstract readonly class FileRelocatedEvent
{
    public function __construct(
        public FileSnapshot $previous,
        public FileSnapshot $file,
        public mixed $user
    ) {
    }
}
