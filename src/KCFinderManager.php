<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel;

use Illuminate\Contracts\Events\Dispatcher;
use KCFinder\Application\FileSelectionService;
use KCFinder\Domain\FileDescriptor;
use Krma\KCFinder\Laravel\Events\FileSelected;

final class KCFinderManager
{
    public function __construct(
        private readonly FileSelectionService $selector,
        private readonly Dispatcher $events
    ) {
    }

    public function select(string $logicalPath): FileDescriptor
    {
        $file = $this->selector->select($logicalPath);
        $this->events->dispatch(new FileSelected($file));
        return $file;
    }
}
