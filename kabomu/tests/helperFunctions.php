<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

use Amp\Future;
use Amp\ByteStream\ReadableBuffer;
use PHPUnit\Framework\Assert;

use AaronicSubstances\Kabomu\Abstractions\QuasiHttpProcessingOptions;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpRequest;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpResponse;

function readAllBytes($stream) {
    return \Amp\ByteStream\buffer($stream);
}

function readBytesFully($source, int $length, Cancellation $cancellation = null): string {
    // allow zero-byte reads to proceed to touch the
    // stream, rather than just return.

    $chunks = [];
    if (!$length) {
        $chunk = $source->read($cancellation);
        if ($chunk !== null) {
            $chunks[] = $chunk;
        }
    }
    $fullChunk = IOUtilsInternal::readBytesAtLeast($source, $chunks, $length, $cancellation);

    if ($chunks) {
        $source->unread($chunks[0]);
    }

    return $fullChunk;
}

function defer() {
    $task = \Amp\async(function () { });
    Future\await([$task]);
}

function createRandomizedReadableBuffer($data) {
    $inputStream = new \Amp\ByteStream\ReadableIterableStream((function () use (&$data) {
        defer();
        $offset = 0;
        while ($offset < strlen($data)) {
            $bytesToCopy = rand(1, strlen($data) - $offset);
            yield substr($data, $offset, $bytesToCopy);
            $offset += $bytesToCopy;
            defer();
        }
    })());
    return $inputStream;
}

function makeStreamUnreadEnabled($src) {
    return new PushbackReadableInternal($src);
}

function createReadableBuffer($data) {
    return new ReadableBuffer($data);
}

function createWritableBuffer() {
    return new WritableBufferInternal();
}

function getWritableBufferContentsNow($dest) {
    return $dest->getContentsNow();
}

function compareRequests(
        ?QuasiHttpRequest $expected, ?QuasiHttpRequest $actual,
        ?string $expectedReqBodyBytes) {
    if (!$expected || !$actual) {
        Assert::assertSame($expected, $actual);
        return;
    }
    Assert::assertEquals($expected->getHttpMethod(), $actual->getHttpMethod());
    Assert::assertEquals($expected->getHttpVersion(), $actual->getHttpVersion());
    Assert::assertEquals($expected->getTarget(), $actual->getTarget());
    Assert::assertEquals($expected->getContentLength(), $actual->getContentLength());
    compareHeaders($expected->getHeaders(), $actual->getHeaders());
    compareBodies($actual->getBody(), $expectedReqBodyBytes);
}

function compareResponses(
        ?QuasiHttpResponse $expected, ?QuasiHttpResponse $actual,
        ?string $expectedResBodyBytes) {
    if (!$expected || !$actual) {
        Assert::assertSame($expected, $actual);
        return;
    }
    Assert::assertSame($expected->getStatusCode(), $actual->getStatusCode());
    Assert::assertSame($expected->getHttpVersion(), $actual->getHttpVersion());
    Assert::assertSame($expected->getHttpStatusMessage(), $actual->getHttpStatusMessage());
    Assert::assertSame($expected->getContentLength(), $actual->getContentLength());
    compareHeaders($expected->getHeaders(), $actual->getHeaders());
    compareBodies($actual->getBody(), $expectedResBodyBytes);
}

function compareBodies($actual, ?string $expectedBodyBytes) {
    if ($expectedBodyBytes === null || !$actual) {
        Assert::assertSame($expectedBodyBytes, $actual);
        return;
    }
    $actualBodyBytes = readAllBytes($actual);
    Assert::assertSame(bin2hex($expectedBodyBytes), bin2hex($actualBodyBytes));
}

function compareHeaders(?array $expected, ?array $actual) {
    if (!$expected || !$actual) {
        Assert::assertEquals($expected, $actual);
        return;
    }
    $expectedExtraction = array();
    foreach ($expected as $key => $value) {
        array_push($expectedExtraction, $key, ...$value);
    }
    $actualExtraction = array();
    foreach ($actual as $key => $value) {
        array_push($actualExtraction, $key, ...$value);
    }
    Assert::assertEquals($expectedExtraction, $actualExtraction);
}

function compareProcessingOptions(
        ?QuasiHttpProcessingOptions $expected,
        ?QuasiHttpProcessingOptions $actual) {
    if (!$expected || !$actual) {
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