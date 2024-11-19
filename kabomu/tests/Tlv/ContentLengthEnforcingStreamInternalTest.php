<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Tlv;

use Amp\Future;
use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\WritableBuffer;
use Amp\PHPUnit\AsyncTestCase;

use AaronicSubstances\Kabomu\Abstractions\PushbackReadableStream;
use AaronicSubstances\Kabomu\Exceptions\KabomuIOException;
use AaronicSubstances\Kabomu\IOUtilsInternal;
use AaronicSubstances\Kabomu\MiscUtilsInternal;

class ContentLengthEnforcingStreamInternalTest extends AsyncTestCase {

    public static function readAllBytes($stream) {
        return \Amp\ByteStream\buffer($stream);
    }

    public static function defer() {
        $task = \Amp\async(function () { });
        Future\await([$task]);
    }

    public static function createRandomizedReadInputStream($data) {
        $inputStream = new \Amp\ByteStream\ReadableIterableStream((function () use (&$data) {
            self::defer();
            $offset = 0;
            while ($offset < strlen($data)) {
                //$bytesToCopy = 1;
                $bytesToCopy = rand(1, strlen($data) - $offset);
                yield substr($data, $offset, $bytesToCopy);
                $offset += $bytesToCopy;
                self::defer();
            }
        })());
        return new PushbackReadableStream($inputStream);
    }

    /**
     * @dataProvider createTestReadingData
    */
    public function testReading(int $contentLength, string $srcData,
            string $expected) {
        // arrange
        $stream = self::createRandomizedReadInputStream(
            MiscUtilsInternal::stringToBytes($srcData));
        $instance = TlvUtils::createContentLengthEnforcingStream(
            $stream, $contentLength);

        // act
        $actual = MiscUtilsInternal::bytesToString(self::readAllBytes($instance));

        // assert
        $this->assertSame($expected, $actual);

        // assert non-repeatability.
        $actual = MiscUtilsInternal::bytesToString(self::readAllBytes($instance));
        $this->assertEmpty($actual);
    }

    public static function createTestReadingData() {
        return [
            [0, '',     ''],
            [0, 'a',      ''],
            [1, 'ab',     "a"],
            [2, 'ab',     "ab"],
            [2, 'abc',    "ab"],
            [3, 'abc',    "abc"],
            [4, 'abcd',   "abcd"],
            [5, 'abcde',  "abcde"],
            [6, 'abcdefghi', 'abcdef']
        ];
    }

    /**
     * @dataProvider createTestReadingForErrorsData
    */
    public function testReadingForErrors(int $contentLength, string $srcData) {
        // arrange
        $stream = new ReadableBuffer(
            MiscUtilsInternal::stringToBytes($srcData));
        $instance = TlvUtils::createContentLengthEnforcingStream(
            $stream, $contentLength);
        $this->expectException(KabomuIOException::class);
        $this->expectExceptionMessage("end of read");

        // act and assert
        self::readAllBytes($instance);
    }

    public static function createTestReadingForErrorsData() {
        return [
            [2, ""],
            [4, "abc"],
            [5, "abcd"],
            [15, "abcdef"]
        ];
    }
    
    public function testZeroByteReads() {
        $stream = new PushbackReadableStream(new ReadableBuffer("\x00\x01\x02\x04"));
        $instance = TlvUtils::createContentLengthEnforcingStream($stream, 3);

        $actual = IOUtilsInternal::readBytesFully($instance, 0);
        $this->assertEmpty($actual);

        $actual = IOUtilsInternal::readBytesFully($instance, 3);
        $this->assertSame("000102", bin2hex($actual));

        $actual = IOUtilsInternal::readBytesFully($instance, 0);
        $this->assertEmpty($actual);

        // test aftermath reads
        $actual = self::readAllBytes($stream);
        $this->assertSame("04", bin2hex($actual));
    }
}