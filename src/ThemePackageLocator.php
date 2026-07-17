<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel;

use Composer\InstalledVersions;

final class ThemePackageLocator
{
    /** @return array<string, string> */
    public function roots(): array
    {
        if (!InstalledVersions::isInstalled('krma-cl/kcfinder-bootstrap5-theme')) {
            return array();
        }

        $packageRoot = InstalledVersions::getInstallPath('krma-cl/kcfinder-bootstrap5-theme');
        $distribution = is_string($packageRoot)
            ? realpath($packageRoot . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'bootstrap5')
            : false;

        return is_string($distribution) && is_dir($distribution)
            ? array('bootstrap5' => $distribution)
            : array();
    }
}
