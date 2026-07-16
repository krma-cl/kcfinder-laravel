<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel;

use KCFinder\Contract\OperationObserverInterface;
use KCFinder\Domain\OperationContext;
use Krma\KCFinder\Laravel\Domain\FileSnapshot;
use LogicException;

final class ClassicBrowserBridge implements OperationObserverInterface
{
    public function __construct(private readonly KCFinderOperationReporter $operations)
    {
    }

    public function before(OperationContext $operation): mixed
    {
        if (
            $operation->resource === OperationContext::RESOURCE_FILE
            && in_array($operation->operation, array('move', 'rename', 'delete'), true)
        ) {
            return $this->operations->snapshot($operation->path, $operation->operation);
        }

        return null;
    }

    public function succeeded(OperationContext $operation, mixed $previousState = null): void
    {
        switch ($operation->operation) {
            case 'upload':
                $this->operations->uploaded($operation->resultingPath());
                return;
            case 'edit':
                $this->operations->edited($operation->resultingPath());
                return;
            case 'move':
                $this->operations->moved($this->previousSnapshot($operation, $previousState), $operation->resultingPath());
                return;
            case 'rename':
                $this->operations->renamed($this->previousSnapshot($operation, $previousState), $operation->resultingPath());
                return;
            case 'delete':
                $this->operations->deleted($this->previousSnapshot($operation, $previousState));
                return;
            case 'create_directory':
                $this->operations->directoryCreated($operation->resultingPath());
                return;
        }

        throw new LogicException('The classic browser emitted an unsupported operation.');
    }

    private function previousSnapshot(OperationContext $operation, mixed $state): FileSnapshot
    {
        if (!$state instanceof FileSnapshot) {
            throw new LogicException(sprintf(
                'The %s operation did not provide its authorized previous snapshot.',
                $operation->operation
            ));
        }
        return $state;
    }
}
