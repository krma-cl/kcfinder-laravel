<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Http;

use RuntimeException;

final class ClassicBrowserBundles
{
    /** @param array<string, string> $themeRoots */
    public function __construct(
        private readonly string $root,
        private readonly array $themeRoots = array()
    ) {
    }

    /**
     * @return array{content: string, contentType: string, output: string, modified: int}|null
     */
    public function render(string $path, ?string $publishedRoot = null): ?array
    {
        $path = $this->legacyPath($path);
        $definition = $this->definition($path);
        if ($definition === null) {
            return null;
        }

        $published = $publishedRoot === null
            ? null
            : $this->publishedFile($publishedRoot, $definition['output']);
        if ($published !== null) {
            $content = file_get_contents($published);
            if (!is_string($content)) {
                throw new RuntimeException('Unable to read the published KCFinder bundle.');
            }
            $content = $this->prepareContent($content, $definition);

            return array(
                'content' => $content,
                'contentType' => $definition['contentType'],
                'output' => $definition['output'],
                'modified' => (int) filemtime($published),
            );
        }

        $files = glob($definition['directory'] . DIRECTORY_SEPARATOR . '*.' . $definition['extension']);
        if (!is_array($files)) {
            throw new RuntimeException('Unable to enumerate KCFinder bundle sources.');
        }
        natcasesort($files);

        $content = '';
        $modified = 0;
        foreach ($files as $file) {
            $realFile = realpath($file);
            if (
                !is_string($realFile)
                || !is_file($realFile)
                || !str_starts_with($realFile, $definition['directory'] . DIRECTORY_SEPARATOR)
            ) {
                continue;
            }
            $source = file_get_contents($realFile);
            if (!is_string($source)) {
                throw new RuntimeException('Unable to read a KCFinder bundle source.');
            }
            $source = $this->prepareContent($source, $definition);
            $content .= $source;
            $modified = max($modified, (int) filemtime($realFile));
        }

        return array(
            'content' => $content,
            'contentType' => $definition['contentType'],
            'output' => $definition['output'],
            'modified' => $modified,
        );
    }

    public function browserUrl(string $legacyPath, ?string $publishedRoot = null): ?string
    {
        $bundle = $this->render($legacyPath, $publishedRoot);
        if ($bundle === null) {
            return null;
        }
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', $bundle['output']);
        $relative = preg_replace('#^bundles/#', 'browser-assets/', $relative);
        if (!is_string($relative)) {
            return null;
        }
        return $relative . '?v=' . substr(hash('sha256', $bundle['content']), 0, 12);
    }

    /** @return array<string, string> output path => content */
    public function all(): array
    {
        $paths = array('js/index.php', 'css/index.php');
        $themes = array();
        $internal = glob($this->root . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        foreach (is_array($internal) ? $internal : array() as $directory) {
            $themes[basename($directory)] = true;
        }
        foreach (array_keys($this->themeRoots) as $theme) {
            $themes[$theme] = true;
        }
        foreach (array_keys($themes) as $theme) {
            if (preg_match('/^[A-Za-z0-9_-]+$/', $theme) !== 1) {
                continue;
            }
            $paths[] = "themes/{$theme}/js.php";
            $paths[] = "themes/{$theme}/css.php";
        }

        $bundles = array();
        foreach ($paths as $path) {
            $bundle = $this->render($path);
            if ($bundle !== null) {
                $bundles[$bundle['output']] = $bundle['content'];
            }
        }
        return $bundles;
    }

    /**
     * @return array{
     *     directory: string,
     *     extension: string,
     *     contentType: string,
     *     output: string,
     *     assetPrefix: string
     * }|null
     */
    private function definition(string $path): ?array
    {
        if ($path === 'js/index.php' || $path === 'css/index.php') {
            $extension = str_starts_with($path, 'js/') ? 'js' : 'css';
            $directory = realpath($this->root . DIRECTORY_SEPARATOR . $extension);
            if (!is_string($directory) || !is_dir($directory)) {
                return null;
            }
            return array(
                'directory' => $directory,
                'extension' => $extension,
                'contentType' => $this->contentType($extension),
                'output' => 'bundles' . DIRECTORY_SEPARATOR . "base.{$extension}",
                'assetPrefix' => '../css/',
            );
        }

        if (preg_match('#^themes/([A-Za-z0-9_-]+)/(js|css)\.php$#', $path, $matches) !== 1) {
            return null;
        }
        $theme = $matches[1];
        $extension = $matches[2];
        $directory = $this->themeRoots[$theme]
            ?? $this->root . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . $theme;
        $realDirectory = realpath($directory);
        if (!is_string($realDirectory) || !is_dir($realDirectory)) {
            return null;
        }

        return array(
            'directory' => $realDirectory,
            'extension' => $extension,
            'contentType' => $this->contentType($extension),
            'output' => 'bundles' . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . "{$theme}.{$extension}",
            'assetPrefix' => "../../themes/{$theme}/",
        );
    }

    /**
     * @param array{
     *     directory: string,
     *     extension: string,
     *     contentType: string,
     *     output: string,
     *     assetPrefix: string
     * } $definition
     */
    private function prepareContent(string $content, array $definition): string
    {
        if ($definition['extension'] !== 'css') {
            return $content;
        }

        $rebased = preg_replace_callback(
            '#/\*.*?\*/(*SKIP)(*F)|url\(\s*(?:(["\'])(.*?)\1|([^)]*?))\s*\)#is',
            function (array $matches) use ($definition): string {
                $quoted = isset($matches[1]) && $matches[1] !== '';
                $value = trim((string) ($quoted ? ($matches[2] ?? '') : ($matches[3] ?? '')));
                if (
                    !$this->isRelativeCssUrl($value)
                    || str_starts_with($value, $definition['assetPrefix'])
                ) {
                    return $matches[0];
                }

                $open = strpos($matches[0], '(');
                $position = $open === false
                    ? false
                    : strpos($matches[0], $value, $open + 1);
                if ($position === false) {
                    return $matches[0];
                }

                return substr_replace(
                    $matches[0],
                    $definition['assetPrefix'] . $value,
                    $position,
                    strlen($value)
                );
            },
            $content
        );
        if (!is_string($rebased)) {
            throw new RuntimeException('Unable to rewrite KCFinder CSS asset URLs.');
        }
        return $rebased;
    }

    private function isRelativeCssUrl(string $url): bool
    {
        if (
            $url === ''
            || str_starts_with($url, '/')
            || str_starts_with($url, '#')
            || str_starts_with($url, '?')
            || str_starts_with(strtolower($url), 'var(')
        ) {
            return false;
        }

        return preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) !== 1;
    }

    private function legacyPath(string $path): string
    {
        return match ($path) {
            'browser-assets/base.js' => 'js/index.php',
            'browser-assets/base.css' => 'css/index.php',
            default => preg_replace_callback(
                '#^browser-assets/themes/([A-Za-z0-9_-]+)\.(js|css)$#',
                static fn (array $matches): string => "themes/{$matches[1]}/{$matches[2]}.php",
                $path
            ) ?? $path,
        };
    }

    private function publishedFile(string $root, string $relative): ?string
    {
        $realRoot = realpath($root);
        $realFile = realpath($root . DIRECTORY_SEPARATOR . $relative);
        if (!is_string($realRoot) || !is_string($realFile) || !is_file($realFile)) {
            return null;
        }
        return str_starts_with($realFile, $realRoot . DIRECTORY_SEPARATOR) ? $realFile : null;
    }

    private function contentType(string $extension): string
    {
        return $extension === 'js'
            ? 'text/javascript; charset=UTF-8'
            : 'text/css; charset=UTF-8';
    }
}
