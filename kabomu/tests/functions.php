<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

use Amp\Future;
use Amp\ByteStream\ReadableBuffer;
use PHPUnit\Framework\Assert;

use AaronicSubstances\Kabomu\Abstractions\QuasiHttpProcessingOptions;
use AaronicSubstances\Kabomu\Tlv\PushbackReadableStream;

function readAllBytes($stream) {
    return \Amp\ByteStream\buffer($stream);
}

function defer() {
    $task = \Amp\async(function () { });
    Future\await([$task]);
}

function createRandomizedReadInputStream($data, $enableUnread = true) {
    $inputStream = new \Amp\ByteStream\ReadableIterableStream((function () use (&$data) {
        defer();
        $offset = 0;
        while ($offset < strlen($data)) {
            //$bytesToCopy = 1;
            $bytesToCopy = rand(1, strlen($data) - $offset);
            yield substr($data, $offset, $bytesToCopy);
            $offset += $bytesToCopy;
            defer();
        }
    })());
    if (!$enableUnread) {
        return $inputStream;
    }
    return new PushbackReadableStream($inputStream);
}

function createUnreadEnabledReadableBuffer($data) {
    return new PushbackReadableStream(new ReadableBuffer($data));
}

function compareProcessingOptions(
        ?QuasiHttpProcessingOptions $expected,
        ?QuasiHttpProcessingOptions $actual) {
    if ($expected === null || $actual === null) {
        TestCase::assertSame($expected, $actual);
        return;
    }
    Assert::assertSame($expected->getMaxResponseBodySize(),
        $actual->getMaxResponseBodySize());
    Assert::assertSame($expected->getTimeoutMillis(),
        $actual->getTimeoutMillis());
    Assert::assertEquals($expected->getExtraConnectivityParams(),
        $actual->getExtraConnectivityParams());
    Assert::assertSame($expected->getMaxHeadersSize(),
        $actual->getMaxHeadersSize());
}