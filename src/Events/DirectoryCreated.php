<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Events;

final readonly class DirectoryCreated
{
    public function __construct(
        public string $path,
        public mixed $user
    ) {
    }
}
