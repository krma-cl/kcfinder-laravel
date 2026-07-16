<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Contracts;

interface ActorResolverInterface
{
    public function resolve(): mixed;
}
