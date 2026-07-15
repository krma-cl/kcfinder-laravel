<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel;

use DateTimeImmutable;
use Illuminate\Filesystem\FilesystemAdapter;
use InvalidArgumentException;
use KCFinder\Contract\UrlResolverInterface;
use KCFinder\Domain\LogicalPath;
use RuntimeException;
use Throwable;

final class StorageUrlResolver implements UrlResolverInterface
{
    public function __construct(
        private readonly FilesystemAdapter $disk,
        private readonly ?string $urlPrefix = null,
        private readonly ?int $temporaryUrlTtl = null
    ) {
        if ($temporaryUrlTtl !== null && $temporaryUrlTtl < 1) {
            throw new InvalidArgumentException('The temporary URL TTL must be positive.');
        }
    }

    public function resolve(string $logicalPath): string
    {
        $logicalPath = LogicalPath::fromString($logicalPath)->value();
        $storagePath = ltrim($logicalPath, '/');

        if ($this->temporaryUrlTtl !== null) {
            try {
                /** @var string $url */
                $url = $this->disk->temporaryUrl(
                    $storagePath,
                    new DateTimeImmutable('+' . $this->temporaryUrlTtl . ' seconds')
                );
                return $url;
            } catch (Throwable) {
                // Some drivers expose the method but do not support temporary URLs.
            }
        }

        if ($this->urlPrefix !== null && $this->urlPrefix !== '') {
            $segments = array_map('rawurlencode', explode('/', $storagePath));
            return rtrim($this->urlPrefix, '/') . '/' . implode('/', $segments);
        }

        try {
            return $this->disk->url($storagePath);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'The selected disk cannot generate public URLs. Configure kcfinder.url_prefix.',
                0,
                $exception
            );
        }
    }
}
