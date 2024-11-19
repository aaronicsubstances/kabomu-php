<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

use Amp\Cancellation;

use AaronicSubstances\Kabomu\Exceptions\KabomuIOException;

class IOUtilsInternal {

    public static function readBytesFully($source, int $length, Cancellation $cancellation = null): string {
        if ($length < 0) {
            throw new Exception("Received negative read length of " . $length);
        }

        // allow zero-byte reads to proceed to touch the
        // stream, rather than just return.
        $chunks = [];

        while (true) {
            $chunk = $source->read($cancellation);
            if ($chunk === null) {
                break;
            }
            $chunks[] = $chunk;
            $length -= strlen($chunk);
            if ($length <= 0) {
                break;
            }
        }

        if ($length > 0) {
            throw KabomuIOException::createEndOfReadError();
        }

        $fullChunk = implode($chunks);

        if ($length < 0) {
            $extraChunk = substr($fullChunk, $length);
            $source->unread($extraChunk);

            $fullChunk = substr($fullChunk, 0, strlen($fullChunk) + $length);
        }

        return $fullChunk;
    }

    public static function copy($readableStream, $writableStream, $cancellation = null) {
        \Amp\ByteStream\pipe($readableStream, $writableStream, $cancellation);
    }
}