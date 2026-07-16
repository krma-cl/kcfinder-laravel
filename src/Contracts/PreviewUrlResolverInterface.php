<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Contracts;

interface PreviewUrlResolverInterface
{
    public function resolve(string $logicalPath): string;
}
