<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

final class ClassicBrowserEntrypoint
{
    private readonly ClassicBrowserBundles $bundles;

    /** @param array<string, string> $themeRoots */
    public function __construct(
        private readonly string $root,
        private readonly array $themeRoots = array(),
        private readonly ?string $publishedAssetsRoot = null
    ) {
        $this->bundles = new ClassicBrowserBundles($root, $themeRoots);
    }

    public function run(string $path, ?Request $request = null): Response
    {
        $path = str_replace('\\', '/', ltrim($path, '/'));
        if (!$this->allowed($path)) {
            abort(404);
        }

        $bundle = $this->bundles->render($path, $this->publishedAssetsRoot);
        if ($bundle !== null) {
            $response = new Response(
                $bundle['content'],
                200,
                array(
                    'Content-Type' => $bundle['contentType'],
                    'Cache-Control' => 'private, max-age=0, must-revalidate',
                    'X-Content-Type-Options' => 'nosniff',
                )
            );
            if ($bundle['modified'] > 0) {
                $response->setLastModified(new \DateTimeImmutable('@' . $bundle['modified']));
            }
            return $response;
        }

        [$file, $allowedRoot] = $this->fileAndRoot($path);
        $realRoot = realpath($allowedRoot);
        $realFile = realpath($file);
        if ($realRoot === false || $realFile === false || !str_starts_with($realFile, $realRoot . DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        if (pathinfo($realFile, PATHINFO_EXTENSION) !== 'php') {
            return new Response(
                (string) file_get_contents($realFile),
                200,
                array(
                    'Content-Type' => $this->contentType($realFile),
                    'Cache-Control' => 'public, max-age=86400',
                    'X-Content-Type-Options' => 'nosniff',
                )
            );
        }

        $cwd = getcwd();
        $server = $this->serverEnvironment($realFile, $request);
        $previousStatus = http_response_code();
        $status = 200;
        $bufferLevel = ob_get_level();
        ob_start();
        try {
            chdir(dirname($realFile));
            require $realFile;
            $body = (string) ob_get_clean();
            $reportedStatus = http_response_code();
            $status = is_int($reportedStatus) && $reportedStatus >= 100 && $reportedStatus <= 599
                ? $reportedStatus
                : 200;
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
            if ($cwd !== false) {
                chdir($cwd);
            }
            $this->restoreServerEnvironment($server);
            if (is_int($previousStatus) && $previousStatus >= 100 && $previousStatus <= 599) {
                http_response_code($previousStatus);
            }
        }

        return new Response($body, $status, array('X-Content-Type-Options' => 'nosniff'));
    }

    /** @return array{0: string, 1: string} */
    private function fileAndRoot(string $path): array
    {
        if (preg_match('#^themes/([A-Za-z0-9_-]+)/(.+)$#', $path, $matches) === 1) {
            $themeRoot = $this->themeRoots[$matches[1]] ?? null;
            if (is_string($themeRoot)) {
                return array(
                    $themeRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $matches[2]),
                    $themeRoot,
                );
            }
        }
        return array(
            $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path),
            $this->root,
        );
    }

    private function allowed(string $path): bool
    {
        if (in_array($path, array('browse.php', 'upload.php', 'js_localize.php', 'css/index.php', 'js/index.php'), true)) {
            return true;
        }
        if (preg_match('#^themes/[A-Za-z0-9_-]+/(?:css|js)\.php$#', $path) === 1) {
            return true;
        }
        return preg_match(
            '#^(?:css|js|themes|lang)/[A-Za-z0-9_./-]+\.(?:css|js|svg|png|gif|jpe?g|webp|ico|woff2?|ttf)$#i',
            $path
        ) === 1 && !str_contains($path, '../');
    }

    private function contentType(string $file): string
    {
        return match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            'css' => 'text/css; charset=UTF-8',
            'js' => 'text/javascript; charset=UTF-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            default => throw new RuntimeException('Unsupported KCFinder asset type.'),
        };
    }

    /**
     * @return array<string, array{exists: bool, value: mixed}>
     */
    private function serverEnvironment(string $realFile, ?Request $request): array
    {
        $values = array(
            'SCRIPT_FILENAME' => $realFile,
            'HTTP_HOST' => $request?->getHttpHost()
                ?? (is_string($_SERVER['HTTP_HOST'] ?? null) ? $_SERVER['HTTP_HOST'] : 'localhost'),
            'HTTPS' => $request !== null
                ? ($request->isSecure() ? 'on' : 'off')
                : (is_string($_SERVER['HTTPS'] ?? null) ? $_SERVER['HTTPS'] : 'off'),
        );
        $previous = array();
        foreach ($values as $key => $value) {
            $previous[$key] = array(
                'exists' => array_key_exists($key, $_SERVER),
                'value' => $_SERVER[$key] ?? null,
            );
            $_SERVER[$key] = $value;
        }
        return $previous;
    }

    /** @param array<string, array{exists: bool, value: mixed}> $previous */
    private function restoreServerEnvironment(array $previous): void
    {
        foreach ($previous as $key => $state) {
            if ($state['exists']) {
                $_SERVER[$key] = $state['value'];
            } else {
                unset($_SERVER[$key]);
            }
        }
    }
}
