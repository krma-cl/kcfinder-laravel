<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Domain;

use InvalidArgumentException;
use JsonSerializable;

final readonly class OperationWarning implements JsonSerializable
{
    public function __construct(
        public string $code,
        public string $message
    ) {
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $code) || $message === '') {
            throw new InvalidArgumentException('Operation warnings require a stable code and message.');
        }
    }

    /** @return array{code: string, message: string} */
    public function toArray(): array
    {
        return array('code' => $this->code, 'message' => $this->message);
    }

    /** @return array{code: string, message: string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
