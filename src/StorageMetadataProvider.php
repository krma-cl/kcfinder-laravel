<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel;

use Illuminate\Filesystem\FilesystemAdapter;
use KCFinder\Contract\FileMetadataProviderInterface;
use KCFinder\Contract\UrlResolverInterface;
use KCFinder\Domain\FileDescriptor;
use KCFinder\Domain\LogicalPath;
use RuntimeException;

final class StorageMetadataProvider implements FileMetadataProviderInterface
{
    public function __construct(
        private readonly FilesystemAdapter $disk,
        private readonly UrlResolverInterface $urlResolver
    ) {
    }

    public function metadata(string $logicalPath): FileDescriptor
    {
        $logicalPath = LogicalPath::fromString($logicalPath)->value();
        $storagePath = ltrim($logicalPath, '/');
        if (!$this->disk->exists($storagePath)) {
            throw new RuntimeException('The requested file does not exist.');
        }

        $size = $this->disk->size($storagePath);
        $mime = $this->disk->mimeType($storagePath) ?: 'application/octet-stream';

        return new FileDescriptor(
            basename($logicalPath),
            $logicalPath,
            $this->urlResolver->resolve($logicalPath),
            strtolower($mime),
            $size
        );
    }
}
