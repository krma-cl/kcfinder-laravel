<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Filesystem\Filesystem;

final class ClearCacheCommand extends Command
{
    protected $signature = 'kcfinder:clear-cache';
    protected $description = 'Clear generated KCFinder CSS, JavaScript and thumbnail cache files';

    public function __construct(
        private readonly Filesystem $files,
        private readonly FilesystemFactory $filesystems,
        private readonly string $coreRoot
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $removed = 0;
        foreach ($this->files->glob($this->coreRoot . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . '*') as $file) {
            if ($this->files->isFile($file) && $this->files->delete($file)) {
                $removed++;
            }
        }

        $disk = $this->filesystems->disk((string) config('kcfinder.disk', 'public'));
        if ($disk->deleteDirectory('.thumbs')) {
            $removed++;
        }

        $this->components->info(sprintf('KCFinder cache cleared (%d entries).', $removed));
        return self::SUCCESS;
    }
}
