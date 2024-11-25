<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Tlv;

use AaronicSubstances\Kabomu\MiscUtilsInternal;

/**
 * Provides functions for writing and reading of data in byte chunks
 * formatted int TlV (ie tag-length-value) format.
 */
class TlvUtils {

    /**
     * Tag number for quasi http headers.
     */
    public const TAG_FOR_QUASI_HTTP_HEADERS = 0x68647273;

    /**
     * Tag number for quasi http body chunks.
     */
    public const TAG_FOR_QUASI_HTTP_BODY_CHUNK = 0x62647461;

    /**
     * Tag number for quasi http body chunk extensions.
     */
    public const TAG_FOR_QUASI_HTTP_BODY_CHUNK_EXT = 0x62657874;

    /**
     * Generates an 8-byte buffer consisting of tag and length.
     * @param int $tag positive number
     * @param int $length non negative number
     * @return string buffer with tag and length serialized
     */
    public static function encodeTagAndLength(int $tag, int $length): string {
        if ($tag <= 0) {
            throw new \InvalidArgumentException("invalid tag: $tag");
        }
        if ($length < 0) {
            throw new \InvalidArgumentException("invalid tag value length: $length");
        }
        $tagAndLen = MiscUtilsInternal::serializeInt32BE($tag) . MiscUtilsInternal::serializeInt32BE($length);
        return $tagAndLen;
    }

    /**
     * Generates an 8-byte buffer consisting of a tag and zero length.
     * @param int $tag positive number to write out
     * @return string buffer with tag and zero length serialized
     */
    public static function generateEndOfTlvStream(int $tag): string {
        return self::encodeTagAndLength($tag, 0);
    }

    /**
     * Decodes a 4-byte buffer slice into a positive number
     * representing a tag.
     * @param string $data source buffer
     * @param int $offset starting position in source buffer
     * @return int decoded positive number
     */
    public static function decodeTag(string $data, int $offset): int {
        $tag = MiscUtilsInternal::deserializeInt32BE($data, $offset);
        if ($tag <= 0) {
            throw new \InvalidArgumentException("invalid tag: $tag");
        }
        return $tag;
    }

    /**
     * Decodes a 4-byte buffer slice into a length.
     * @param string $data source buffer
     * @param string $offset starting position in source buffer
     * @return int The decoded length is negative.
     */
    public static function decodeLength(string $data, int $offset): int {
        $decodedLength = MiscUtilsInternal::deserializeInt32BE($data, $offset);
        if ($decodedLength < 0) {
            throw new \InvalidArgumentException("invalid tag value length: " .
                $decodedLength);
        }
        return $decodedLength;
    }

    /**
     * Creates a stream which wraps another stream to
     * ensure that a given amount of bytes are read from it.
     * @param mixed $stream the readable stream to read from
     * @param int $length the expected number of bytes to read from stream
     * argument. Must not be negative.
     * @param ?string $initialData optional string with which to prepend the $stream contents.
     * @return mixed stream which enforces a certain length on
     * readable stream argument
     */
    public static function createContentLengthEnforcingStream($stream, int $length, ?string $initialData = null) {
        return new ContentLengthEnforcingStreamInternal($stream, $length, $initialData);
    }

    /**
     * Creates a stream which wraps another stream to ensure that
     * a given amount of bytes are not exceeded when reading from it.
     * @param mixed $stream the readable stream to read from
     * @param int $maxLength the number of bytes beyond which
     * reads will fail. Can be zero, in which case a default of 128MB
     * will be used.
     * @return mixed stream which enforces a maximum length on readable
     * stream argument.
     */
    public static function createMaxLengthEnforcingStream($stream, int $maxLength = 0) {
        return new MaxLengthEnforcingStreamInternal($stream, $maxLength);
    }

    /**
     * Creates a stream which wraps another stream to decode
     * TLV-encoded byte chunks from it.
     * @param mixed $stream the readable stream to read from
     * @param int $expectedTag the tag of the byte chunks
     * @param ?int $tagToIgnore the tag of any optional byte chunk
     * preceding chunks with the expected tag.
     * @param ?string $initialData optional string with which to prepend the $stream contents.
     * @return mixed stream which decodes TLV-encoded bytes chunks.
     */
    public static function createTlvDecodingReadableStream($stream,
            int $expectedTag, ?int $tagToIgnore = null, ?string $initialData = null) {
        return new BodyChunkDecodingStreamInternal($stream, $expectedTag,
            $tagToIgnore, $initialData);
    }

    /**
     * Creates a stream which wraps another stream to encode
     * byte chunks into it in TLV format.
     * @param $backingStream the readable stream to read from
     * @param $tagToUse the tag to use to encode byte chunks
     * @return mixed stream which encodes byte chunks in TLV format
     */
    public static function createTlvEncodingReadableStream($stream, $tagToUse) {
        return new BodyChunkEncodingStreamInternal($stream, $tagToUse);
    }
}