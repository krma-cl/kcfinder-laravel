<?php

declare(strict_types=1);

namespace Krma\KCFinder\Laravel\Tests;

use KCFinder\Domain\FileDescriptor;
use Krma\KCFinder\Laravel\Domain\OperationResult;
use Krma\KCFinder\Laravel\Domain\OperationWarning;
use PHPUnit\Framework\TestCase;

final class OperationResultTest extends TestCase
{
    public function testItSerializesAStableVersionedSuccessPayload(): void
    {
        $file = new FileDescriptor('report.pdf', '/docs/report.pdf', '/storage/docs/report.pdf', 'application/pdf', 184320);
        $result = OperationResult::success('upload', array(
            $file,
        ), array(
            new OperationWarning('CATALOG_PENDING', 'The file was saved but catalog synchronization is pending.'),
        ));

        self::assertSame(200, $result->httpStatus());
        self::assertSame(array(
            'success' => true,
            'operation' => 'upload',
            'files' => array($file->toArray()),
            'warnings' => array(array(
                'code' => 'CATALOG_PENDING',
                'message' => 'The file was saved but catalog synchronization is pending.',
            )),
            'meta' => array('version' => 1),
        ), $result->toArray());
    }

    public function testItSerializesAStableFailureAndHttpStatus(): void
    {
        $result = OperationResult::failure('upload', 'MIME_NOT_ALLOWED', 'The detected file type is not allowed.', 415);

        self::assertSame(415, $result->httpStatus());
        self::assertSame(array(
            'success' => false,
            'operation' => 'upload',
            'files' => array(),
            'warnings' => array(),
            'meta' => array('version' => 1),
            'error' => array(
                'code' => 'MIME_NOT_ALLOWED',
                'message' => 'The detected file type is not allowed.',
                'status' => 415,
            ),
        ), $result->jsonSerialize());
    }
}
