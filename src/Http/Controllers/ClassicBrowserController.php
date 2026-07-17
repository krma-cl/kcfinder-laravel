<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Response;
use Krma\KCFinder\Laravel\Http\ClassicBrowserEntrypoint;
use Krma\KCFinder\Laravel\Http\ClassicBrowserRuntime;

final class ClassicBrowserController
{
    public function __construct(
        private readonly Gate $gate,
        private readonly ClassicBrowserRuntime $runtime,
        private readonly ClassicBrowserEntrypoint $entrypoint
    ) {
    }

    public function __invoke(string $path = 'browse.php'): Response
    {
        $ability = (string) config('kcfinder.gate_ability', 'kcfinder.select');
        $this->gate->authorize($ability, array('browse', '/'));
        $this->runtime->prepare();

        $response = $this->entrypoint->run($path);
        foreach ((array) config('kcfinder.http.headers', array()) as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $response->headers->set($name, $value);
            }
        }
        return $response;
    }
}
