<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Tests;

use Krma\KCFinder\Laravel\Http\NativeSessionInitializer;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class NativeSessionInitializerTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFirstRequestMakesTheIssuedCsrfTokenImmediatelyAvailable(): void
    {
        $_SESSION = array();
        $_COOKIE = array();
        $path = sys_get_temp_dir() . '/kcfinder-native-session-' . bin2hex(random_bytes(4));

        $token = (new NativeSessionInitializer())->initialize(array(
            'name' => 'KCFINDERTEST',
            'save_path' => $path,
            'cookie_path' => '/',
        ));

        self::assertSame($token, $_SESSION['kcCsrf']);
        self::assertSame($token, $_COOKIE['kcCsrf']);
        self::assertNotSame('', $token);
        session_write_close();
    }
}
