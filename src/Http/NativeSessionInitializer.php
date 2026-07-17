<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Http;

use RuntimeException;

final class NativeSessionInitializer
{
    /** @param array<string, mixed> $config */
    public function initialize(array $config): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $savePath = $config['save_path'] ?? null;
            if (is_string($savePath) && $savePath !== '') {
                if (!is_dir($savePath) && !mkdir($savePath, 0770, true) && !is_dir($savePath)) {
                    throw new RuntimeException('Unable to create the KCFinder session directory.');
                }
                session_save_path($savePath);
            }

            $name = $config['name'] ?? 'KCFINDERSESSID';
            if (is_string($name) && $name !== '') {
                session_name($name);
            }
            if (!session_start()) {
                throw new RuntimeException('Unable to start the native KCFinder session.');
            }
        }

        $token = $_SESSION['kcCsrf'] ?? null;
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION['kcCsrf'] = $token;
        }

        $_COOKIE['kcCsrf'] = $token;
        $sameSite = $config['same_site'] ?? 'Lax';
        if (!in_array($sameSite, array('Lax', 'lax', 'None', 'none', 'Strict', 'strict'), true)) {
            throw new RuntimeException('The KCFinder session SameSite value is invalid.');
        }

        if (!headers_sent()) {
            setcookie('kcCsrf', $token, array(
                'expires' => 0,
                'path' => (string) ($config['cookie_path'] ?? '/'),
                'domain' => (string) ($config['cookie_domain'] ?? ''),
                'secure' => (bool) ($config['secure'] ?? false),
                'httponly' => true,
                'samesite' => $sameSite,
            ));
        }

        return $token;
    }
}
