<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel;

use Illuminate\Filesystem\FilesystemAdapter;
use InvalidArgumentException;
use KCFinder\Domain\LogicalPath;
use Krma\KCFinder\Laravel\Contracts\ChecksumProviderInterface;
use Throwable;

final class StorageChecksumProvider implements ChecksumProviderInterface
{
    public function __construct(
        private readonly FilesystemAdapter $disk,
        private readonly ?string $algorithm = 'sha256'
    ) {
        if ($algorithm !== null && !in_array($algorithm, hash_algos(), true)) {
            throw new InvalidArgumentException('The configured checksum algorithm is not supported.');
        }
    }

    public function checksum(string $logicalPath): ?string
    {
        if ($this->algorithm === null) {
            return null;
        }

        $storagePath = ltrim(LogicalPath::fromString($logicalPath)->value(), '/');
        try {
            $stream = $this->disk->readStream($storagePath);
            if (!is_resource($stream)) {
                return null;
            }

            try {
                $context = hash_init($this->algorithm);
                hash_update_stream($context, $stream);
                return hash_final($context);
            } finally {
                fclose($stream);
            }
        } catch (Throwable) {
            return null;
        }
    }
}
