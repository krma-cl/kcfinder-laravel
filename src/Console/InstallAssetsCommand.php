<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Console;

use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Krma\KCFinder\Laravel\Http\ClassicBrowserBundles;

final class InstallAssetsCommand extends Command
{
    protected $signature = 'kcfinder:install-assets {--force : Replace existing published assets}';
    protected $description = 'Publish KCFinder static browser assets without exposing PHP files from vendor';
    private readonly ClassicBrowserBundles $bundles;

    public function __construct(
        private readonly Filesystem $files,
        private readonly string $coreRoot,
        ?ClassicBrowserBundles $bundles = null
    ) {
        parent::__construct();
        $this->bundles = $bundles ?? new ClassicBrowserBundles($coreRoot);
    }

    public function handle(): int
    {
        $configuredTarget = config('kcfinder.http.assets_path');
        $target = is_string($configuredTarget) && $configuredTarget !== ''
            ? $configuredTarget
            : public_path(trim((string) config('kcfinder.http.prefix', 'kcfinder'), '/'));
        if ($this->files->isDirectory($target) && !$this->option('force')) {
            $this->components->error('Assets already exist. Use --force to replace them.');
            return self::FAILURE;
        }

        $this->files->deleteDirectory($target);
        $this->files->ensureDirectoryExists($target);
        foreach (array('css', 'js', 'themes') as $directory) {
            $source = $this->coreRoot . DIRECTORY_SEPARATOR . $directory;
            if (!$this->files->isDirectory($source)) {
                continue;
            }
            foreach ($this->files->allFiles($source) as $file) {
                if (strtolower($file->getExtension()) === 'php') {
                    continue;
                }
                $relative = $file->getRelativePathname();
                $destination = $target . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR . $relative;
                $this->files->ensureDirectoryExists(dirname($destination));
                $this->files->copy($file->getPathname(), $destination);
            }
        }

        foreach ($this->bundles->all() as $relative => $content) {
            $destination = $target . DIRECTORY_SEPARATOR . $relative;
            $this->files->ensureDirectoryExists(dirname($destination));
            $this->files->put($destination, $content);
        }

        $themeVersion = null;
        if (InstalledVersions::isInstalled('krma-cl/kcfinder-bootstrap5-theme')) {
            $themeRoot = InstalledVersions::getInstallPath('krma-cl/kcfinder-bootstrap5-theme');
            $themeSource = is_string($themeRoot)
                ? $themeRoot . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'bootstrap5'
                : null;
            if (is_string($themeSource) && $this->files->isDirectory($themeSource)) {
                $themeTarget = $target . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'bootstrap5';
                $this->files->deleteDirectory($themeTarget);
                $this->copyStaticDirectory($themeSource, $themeTarget);
                $themeVersion = InstalledVersions::getPrettyVersion('krma-cl/kcfinder-bootstrap5-theme');
            }
        }

        $version = InstalledVersions::getPrettyVersion('krma-cl/kcfinder') ?? 'unknown';
        $this->files->put(
            $target . DIRECTORY_SEPARATOR . 'manifest.json',
            json_encode(
                array('core' => $version, 'bootstrap5Theme' => $themeVersion),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ) . PHP_EOL
        );
        $this->components->info(sprintf('KCFinder assets %s published to %s.', $version, $target));
        return self::SUCCESS;
    }

    private function copyStaticDirectory(string $source, string $target): void
    {
        foreach ($this->files->allFiles($source) as $file) {
            if (strtolower($file->getExtension()) === 'php') {
                continue;
            }
            $destination = $target . DIRECTORY_SEPARATOR . $file->getRelativePathname();
            $this->files->ensureDirectoryExists(dirname($destination));
            $this->files->copy($file->getPathname(), $destination);
        }
    }
}
