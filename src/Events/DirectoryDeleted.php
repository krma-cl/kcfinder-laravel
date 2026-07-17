<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Events;

final readonly class DirectoryDeleted
{
    public function __construct(
        public string $path,
        public mixed $user
    ) {
    }
}
