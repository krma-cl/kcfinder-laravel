<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Domain;

use JsonSerializable;
use KCFinder\Domain\FileDescriptor;

final readonly class FileSnapshot implements JsonSerializable
{
    public function __construct(
        public FileDescriptor $file,
        public ?string $checksum
    ) {
    }

    /** @return array{name: string, path: string, url: string, mime: string, size: int, checksum: ?string} */
    public function toArray(): array
    {
        return array_merge($this->file->toArray(), array('checksum' => $this->checksum));
    }

    /** @return array{name: string, path: string, url: string, mime: string, size: int, checksum: ?string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
