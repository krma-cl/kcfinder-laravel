<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Contracts;

interface ChecksumProviderInterface
{
    public function checksum(string $logicalPath): ?string;
}
