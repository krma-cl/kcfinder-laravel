<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Domain;

use InvalidArgumentException;
use JsonSerializable;

final readonly class OperationError implements JsonSerializable
{
    public function __construct(
        public string $code,
        public string $message,
        public int $status
    ) {
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $code) || $message === '' || $status < 400 || $status > 599) {
            throw new InvalidArgumentException('Operation errors require a stable code, message and HTTP error status.');
        }
    }

    /** @return array{code: string, message: string, status: int} */
    public function toArray(): array
    {
        return array('code' => $this->code, 'message' => $this->message, 'status' => $this->status);
    }

    /** @return array{code: string, message: string, status: int} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
