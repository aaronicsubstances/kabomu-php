<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

use Amp\Cancellation;

use AaronicSubstances\Kabomu\Exceptions\KabomuIOException;

class IOUtilsInternal {

    public static function readBytesAtLeast($source, int $length, array &$dest, Cancellation $cancellation = null) {
        if ($length < 0) {
            throw new Exception("Received negative read length of " . $length);
        }

        // allow zero-byte reads to proceed to touch the
        // stream, rather than just return.

        while (true) {
            $chunk = $source->read($cancellation);
            if ($chunk === null) {
                break;
            }
            $dest[] = $chunk;
            $length -= strlen($chunk);
            if ($length <= 0) {
                break;
            }
        }

        if ($length > 0) {
            throw KabomuIOException::createEndOfReadError();
        }

    }

    public static function readBytesFully($source, int $length, Cancellation $cancellation = null): string {
        $chunks = [];
        self::readBytesAtLeast($source, $length, $chunks, $cancellation);

        $fullChunk = implode($chunks);
        $readLen = strlen($fullChunk);
        
        if ($readLen > $length) {
            $extraChunk = substr($fullChunk, $length - $readLen);
            $source->unread($extraChunk);

            $fullChunk = substr($fullChunk, 0, $length);
        }

        return $fullChunk;
    }

    public static function copy($readableStream, $writableStream, $cancellation = null) {
        \Amp\ByteStream\pipe($readableStream, $writableStream, $cancellation);
    }
}