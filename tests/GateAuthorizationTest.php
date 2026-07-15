<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Tests;

use Illuminate\Contracts\Auth\Access\Gate;
use Krma\KCFinder\Laravel\GateAuthorization;
use PHPUnit\Framework\TestCase;

final class GateAuthorizationTest extends TestCase
{
    public function testItPassesOperationAndPathToTheConfiguredAbility(): void
    {
        $gate = $this->createMock(Gate::class);
        $gate->expects(self::once())
            ->method('allows')
            ->with('kcfinder.select', array('select', '/docs/report.pdf'))
            ->willReturn(true);

        self::assertTrue((new GateAuthorization($gate, 'kcfinder.select'))->can('select', '/docs/report.pdf'));
    }
}
