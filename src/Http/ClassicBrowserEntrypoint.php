<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Http;

use Illuminate\Http\Response;
use RuntimeException;

final class ClassicBrowserEntrypoint
{
    public function __construct(private readonly string $root)
    {
    }

    public function run(string $path): Response
    {
        $path = str_replace('\\', '/', ltrim($path, '/'));
        if (!$this->allowed($path)) {
            abort(404);
        }

        $file = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $realRoot = realpath($this->root);
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
        $status = 200;
        ob_start();
        try {
            chdir($this->root);
            require $realFile;
            $body = (string) ob_get_clean();
            $reportedStatus = http_response_code();
            $status = is_int($reportedStatus) && $reportedStatus >= 100 && $reportedStatus <= 599
                ? $reportedStatus
                : 200;
        } finally {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            if ($cwd !== false) {
                chdir($cwd);
            }
        }

        return new Response($body, $status, array('X-Content-Type-Options' => 'nosniff'));
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
}
