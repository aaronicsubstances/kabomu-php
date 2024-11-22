<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

use Amp\Cancellation;

use AaronicSubstances\Kabomu\Exceptions\KabomuIOException;

class IOUtilsInternal {

    public static function readBytesAtLeast($source, array &$dest, int $length, Cancellation $cancellation = null) {
        if ($length < 0) {
            throw new Exception("Received negative read length of " . $length);
        }

        $origDestCount = count($dest);
        $remLength = $length;
        $origDestCountUsed = 0;

        while ($remLength > 0 && $origDestCountUsed < $origDestCount) {
            $remLength -= strlen($dest[$origDestCountUsed]);
            $origDestCountUsed++;
        }

        if ($remLength <= 0) {
            // allow zero-byte reads to proceed to touch the
            // stream, rather than just return.
            if ($origDestCount && !$length) {
                return "";
            }

            $result = [];
            for ($i = 0; $i < $origDestCountUsed - 1; $i++) {
                $result[] = array_shift($dest);
            }
            if ($remLength) {
                $lastChunk = $dest[$origDestCountUsed - 1];
                $divide_pt = strlen($lastChunk) + $remLength;
                $result[] = substr($lastChunk, 0, $divide_pt);
                $dest[$origDestCountUsed - 1] = substr($lastChunk, $divide_pt);
            }
            else {
                $result[] = array_shift($dest);
            }

            return implode($result);
        }

        while (true) {
            $chunk = $source->read($cancellation);
            if ($chunk === null) {
                break;
            }
            $dest[] = $chunk;
            $remLength -= strlen($chunk);
            if ($remLength <= 0) {
                break;
            }
        }

        if ($remLength > 0) {
            throw KabomuIOException::createEndOfReadError();
        }

        // NB: reusing $dest for creating $fullChunk
        // rather than creating another array like $result above.

        if ($remLength) {
            $lastDestIdx = count($dest) - 1;
            $lastChunk = $dest[$lastDestIdx];

            $divide_pt = strlen($lastChunk) + $remLength;
            $dest[$lastDestIdx] = substr($lastChunk, 0, $divide_pt);

            $fullChunk = implode($dest);
            $dest = [ substr($lastChunk, $divide_pt) ];
        }
        else {
            $fullChunk = implode($dest);
            $dest = [];
        }

        return $fullChunk;
    }

    public static function readBytesFully($source, int $length, Cancellation $cancellation = null): string {
        $chunks = [];
        $fullChunk = self::readBytesAtLeast($source, $chunks, $length, $cancellation);

        if (!empty($chunks)) {
            $unshift = $chunks[0];
            $source->unread($unshift);
        }

        return $fullChunk;
    }

    public static function copy($readableStream, $writableStream, $cancellation = null) {
        \Amp\ByteStream\pipe($readableStream, $writableStream, $cancellation);
    }
}