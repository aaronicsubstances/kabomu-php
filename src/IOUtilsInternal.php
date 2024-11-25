<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

use Amp\Cancellation;

use AaronicSubstances\Kabomu\Exceptions\KabomuIOException;

class IOUtilsInternal {

    public static function readBytesAtLeast($source, array &$dest, int $length, Cancellation $cancellation = null) {
        if ($length < 0) {
            throw new \InvalidArgumentException("Received negative read length of " . $length);
        }

        if (!$length) {
            return "";
        }

        $remLength = $length;
        $lastChunkLen = 0; // to avoid calling another strlen in the end.
        $destCount = \count($dest); // to avoid counting $dest items in the end.

        // ensure at most 1 entry in dest.
        if ($destCount > 1) {
            $dest = [ \implode($dest) ];
            $destCount = 1;
        }

        if ($dest) {
            $lastChunkLen = \strlen($dest[0]);
            $remLength -= $lastChunkLen;
        }

        while ($remLength > 0) {
            $chunk = $source->read($cancellation);
            if ($chunk === null) {
                throw KabomuIOException::createEndOfReadError();
            }

            $dest[] = $chunk;
            $destCount++;

            $lastChunkLen = \strlen($chunk);
            $remLength -= $lastChunkLen;
        }

        // ensure at most 1 entry in dest in the end.

        // NB: reusing $dest for creating $fullChunk
        // rather than creating another array.

        if ($remLength) {
            $lastChunk = $dest[$destCount - 1];

            $divide_pt = $lastChunkLen + $remLength;
            $dest[$destCount - 1] = \substr($lastChunk, 0, $divide_pt);

            if ($destCount === 1) {
                $fullChunk = $dest[0];
            }
            else {
                $fullChunk = \implode($dest);
            }
            $dest = [ \substr($lastChunk, $divide_pt) ];
        }
        else {
            if ($destCount === 1) {
                $fullChunk = $dest[0];
            }
            else {
                $fullChunk = \implode($dest);
            }
            $dest = [];
        }

        return $fullChunk;
    }

    public static function copy($readableStream, $writableStream, $cancellation = null) {
        \Amp\ByteStream\pipe($readableStream, $writableStream, $cancellation);
    }
}