<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Tlv;

use Amp\Future;
use Amp\ByteStream\ReadableBuffer;
use Amp\PHPUnit\AsyncTestCase;

use AaronicSubstances\Kabomu\IOUtilsInternal;
use AaronicSubstances\Kabomu\MiscUtilsInternal;
use AaronicSubstances\Kabomu\Exceptions\KabomuIOException;
use AaronicSubstances\Kabomu\Tlv\PushbackReadableStream;

class MaxLengthEnforcingStreamInternalTest extends AsyncTestCase {

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
    function testReading(int $maxLength, string $expected) {
        // arrange
        $stream = self::createRandomizedReadInputStream(
            MiscUtilsInternal::stringToBytes($expected));
        $instance = TlvUtils::createMaxLengthEnforcingStream(
            $stream, $maxLength);

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
            [0, ''],
            [0, "a",],
            [2, "a"],
            [2, "ab",],
            [3, "a"],
            [3, "abc"],
            [4, "abcd"],
            [5, "abcde"],
            [60, "abcdefghi"]
        ];
    }

    /**
     * @dataProvider createTestReadingForErrorsData
    */
    public function testReadingForErrors(int $maxLength, string $srcData) {
        // arrange
        $stream = new ReadableBuffer(
            MiscUtilsInternal::stringToBytes($srcData));
        $instance = TlvUtils::createMaxLengthEnforcingStream(
            $stream, $maxLength);
        $this->expectException(KabomuIOException::class);
        $this->expectExceptionMessage("exceeds limit of $maxLength");

        // act and assert
        self::readAllBytes($instance);
    }

    public static function createTestReadingForErrorsData() {
        return [
            [1, "ab"],
            [2, "abc"],
            [3, "abcd"],
            [5, "abcdefxyz"]
        ];
    }

    public function testZeroByteReads() {
        $stream = new PushbackReadableStream(new ReadableBuffer("\x00\x01\x02\x04"));
        $instance = TlvUtils::createMaxLengthEnforcingStream($stream, 4);

        $actual = IOUtilsInternal::readBytesFully($instance, 0);
        $this->assertEmpty(bin2hex($actual));

        $actual = IOUtilsInternal::readBytesFully($instance, 3);
        $this->assertSame("000102", bin2hex($actual));

        $actual = self::readAllBytes($instance);
        $this->assertSame("04", bin2hex($actual));

        $actual = IOUtilsInternal::readBytesFully($instance, 0);
        $this->assertEmpty(bin2hex($actual));
    }
}