<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Tests;

use Illuminate\Contracts\Events\Dispatcher;
use KCFinder\Application\FileSelectionService;
use KCFinder\Contract\AuthorizationInterface;
use KCFinder\Contract\FileMetadataProviderInterface;
use KCFinder\Exception\AuthorizationDenied;
use Krma\KCFinder\Laravel\Contracts\PreviewUrlResolverInterface;
use Krma\KCFinder\Laravel\KCFinderManager;
use PHPUnit\Framework\TestCase;

final class KCFinderManagerTest extends TestCase
{
    public function testPreviewUrlsHaveTheirOwnAuthorizationAndResolver(): void
    {
        $events = $this->createMock(Dispatcher::class);
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->expects(self::once())->method('can')->with('preview', '/private/photo.jpg')->willReturn(true);
        $resolver = $this->createMock(PreviewUrlResolverInterface::class);
        $resolver->expects(self::once())->method('resolve')->with('/private/photo.jpg')->willReturn('/kcfinder/preview/private/photo.jpg');

        $manager = new KCFinderManager($this->selector($authorization), $events, null, $authorization, $resolver);

        self::assertSame('/kcfinder/preview/private/photo.jpg', $manager->previewUrl('/private/photo.jpg'));
    }

    public function testPreviewAuthorizationCannotBeBypassed(): void
    {
        $events = $this->createMock(Dispatcher::class);
        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->method('can')->willReturn(false);
        $resolver = $this->createMock(PreviewUrlResolverInterface::class);
        $resolver->expects(self::never())->method('resolve');

        $this->expectException(AuthorizationDenied::class);
        (new KCFinderManager($this->selector($authorization), $events, null, $authorization, $resolver))
            ->previewUrl('/private/photo.jpg');
    }

    private function selector(AuthorizationInterface $authorization): FileSelectionService
    {
        return new FileSelectionService(
            $this->createMock(FileMetadataProviderInterface::class),
            $authorization
        );
    }
}
