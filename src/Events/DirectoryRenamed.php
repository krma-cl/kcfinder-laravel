<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Events;

final readonly class DirectoryRenamed
{
    public function __construct(
        public string $previousPath,
        public string $path,
        public mixed $user
    ) {
    }
}
