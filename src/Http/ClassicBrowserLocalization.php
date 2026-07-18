<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Http;

use Illuminate\Http\Response;
use JsonException;

final class ClassicBrowserLocalization
{
    public function __construct(private readonly string $root)
    {
    }

    /** @throws JsonException */
    public function response(?string $language): Response
    {
        $labels = array();
        $modified = 0;
        $file = $this->languageFile($language);
        if ($file !== null) {
            $translated = $this->load($file);
            foreach ($translated as $english => $native) {
                if (is_string($english) && is_string($native) && !str_starts_with($english, '_')) {
                    $labels[$english] = $native;
                }
            }
            $modified = (int) filemtime($file);
        }

        $response = new Response(
            '_.labels=' . json_encode(
                $labels,
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
            ) . ';',
            200,
            array(
                'Content-Type' => 'text/javascript; charset=UTF-8',
                'Cache-Control' => 'private, max-age=0, must-revalidate',
                'X-Content-Type-Options' => 'nosniff',
            )
        );
        if ($modified > 0) {
            $response->setLastModified(new \DateTimeImmutable('@' . $modified));
        }
        return $response;
    }

    private function languageFile(?string $language): ?string
    {
        if (
            !is_string($language)
            || $language === ''
            || $language === 'en'
            || preg_match('/^[A-Za-z0-9_-]+$/', $language) !== 1
        ) {
            return null;
        }

        $root = realpath($this->root . DIRECTORY_SEPARATOR . 'lang');
        $file = realpath($this->root . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . $language . '.php');
        if (!is_string($root) || !is_string($file) || !is_file($file)) {
            return null;
        }
        return str_starts_with($file, $root . DIRECTORY_SEPARATOR) ? $file : null;
    }

    /** @return array<mixed, mixed> */
    private function load(string $file): array
    {
        return (static function (string $languageFile): array {
            $lang = array();
            require $languageFile;
            return $lang;
        })($file);
    }
}
