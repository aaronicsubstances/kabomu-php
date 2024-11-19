<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Tlv;

class TlvUtils {

    /**
     * Creates a stream which wraps another stream to
     * ensure that a given amount of bytes are read from it.
     * @param mixed stream the readable stream to read from
     * @param int length the expected number of bytes to read from stream
     * argument. Must not be negative.
     * @return mixed stream which enforces a certain length on
     * readable stream argument
     */
    public static function createContentLengthEnforcingStream(mixed $stream,
            int $length) {
        return new ContentLengthEnforcingStreamInternal($stream, $length);
    }

    /**
     * Creates a stream which wraps another stream to ensure that
     * a given amount of bytes are not exceeded when reading from it.
     * @param mixed stream the readable stream to read from
     * @param int $maxLength the number of bytes beyond which
     * reads will fail. Can be zero, in which case a default of 128MB
     * will be used.
     * @return mixed stream which enforces a maximum length on readable
     * stream argument.
     */
    public static function createMaxLengthEnforcingStream(mixed $stream,
            int $maxLength = 0) {
        return new MaxLengthEnforcingStreamInternal($stream, $maxLength);
    }
}