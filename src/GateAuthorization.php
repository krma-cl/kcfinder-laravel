<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel;

use Illuminate\Contracts\Auth\Access\Gate;
use KCFinder\Contract\AuthorizationInterface;

final class GateAuthorization implements AuthorizationInterface
{
    public function __construct(
        private readonly Gate $gate,
        private readonly string $ability
    ) {
    }

    public function can(string $operation, string $logicalPath): bool
    {
        return $this->gate->allows($this->ability, array($operation, $logicalPath));
    }
}
