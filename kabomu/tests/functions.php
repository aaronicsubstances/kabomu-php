<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

use Amp\Future;
use Amp\ByteStream\ReadableBuffer;

use AaronicSubstances\Kabomu\Tlv\PushbackReadableStream;

function readAllBytes($stream) {
    return \Amp\ByteStream\buffer($stream);
}

function defer() {
    $task = \Amp\async(function () { });
    Future\await([$task]);
}

function createRandomizedReadInputStream($data) {
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
    return new PushbackReadableStream($inputStream);
}

function createUnreadEnabledReadableBuffer($data) {
    return new PushbackReadableStream(new ReadableBuffer($data));
}