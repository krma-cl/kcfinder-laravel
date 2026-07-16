<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Krma\KCFinder\Laravel\Contracts\ActorResolverInterface;

final class AuthActorResolver implements ActorResolverInterface
{
    public function __construct(private readonly AuthFactory $auth)
    {
    }

    public function resolve(): mixed
    {
        return $this->auth->guard()->user();
    }
}
