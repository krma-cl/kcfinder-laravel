<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Domain;

use InvalidArgumentException;
use JsonSerializable;
use KCFinder\Domain\FileDescriptor;

final readonly class OperationResult implements JsonSerializable
{
    /** @var array<int, FileDescriptor> */
    public array $files;

    /** @var array<int, OperationWarning> */
    public array $warnings;

    /**
     * @param array<int, mixed> $files
     * @param array<int, mixed> $warnings
     */
    private function __construct(
        public bool $success,
        public string $operation,
        array $files,
        array $warnings,
        public ?OperationError $error
    ) {
        if (!preg_match('/^[a-z][a-z_]*$/', $operation)) {
            throw new InvalidArgumentException('The operation name is invalid.');
        }
        $this->files = self::validatedFiles($files);
        $this->warnings = self::validatedWarnings($warnings);
        if ($success === ($error !== null)) {
            throw new InvalidArgumentException('Successful results cannot contain errors and failures require one.');
        }
    }

    /**
     * @param array<int, FileDescriptor> $files
     * @param array<int, OperationWarning> $warnings
     */
    public static function success(string $operation, array $files = array(), array $warnings = array()): self
    {
        return new self(true, $operation, array_values($files), array_values($warnings), null);
    }

    /** @param array<int, OperationWarning> $warnings */
    public static function failure(
        string $operation,
        string $code,
        string $message,
        int $status = 422,
        array $warnings = array()
    ): self {
        return new self(false, $operation, array(), array_values($warnings), new OperationError($code, $message, $status));
    }

    public function httpStatus(): int
    {
        return $this->error === null ? 200 : $this->error->status;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $result = array(
            'success' => $this->success,
            'operation' => $this->operation,
            'files' => array_map(static fn (FileDescriptor $file): array => $file->toArray(), $this->files),
            'warnings' => array_map(static fn (OperationWarning $warning): array => $warning->toArray(), $this->warnings),
            'meta' => array('version' => 1),
        );
        if ($this->error !== null) {
            $result['error'] = $this->error->toArray();
        }
        return $result;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param array<int, mixed> $files
     * @return array<int, FileDescriptor>
     */
    private static function validatedFiles(array $files): array
    {
        $validated = array();
        foreach ($files as $file) {
            if (!$file instanceof FileDescriptor) {
                throw new InvalidArgumentException('Every operation file must be a file descriptor.');
            }
            $validated[] = $file;
        }
        return $validated;
    }

    /**
     * @param array<int, mixed> $warnings
     * @return array<int, OperationWarning>
     */
    private static function validatedWarnings(array $warnings): array
    {
        $validated = array();
        foreach ($warnings as $warning) {
            if (!$warning instanceof OperationWarning) {
                throw new InvalidArgumentException('Every operation warning must be structured.');
            }
            $validated[] = $warning;
        }
        return $validated;
    }
}
