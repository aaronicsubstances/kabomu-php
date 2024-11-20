<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Tlv;

use Amp\Future;
use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\WritableBuffer;
use Amp\PHPUnit\AsyncTestCase;

use AaronicSubstances\Kabomu\IOUtilsInternal;
use AaronicSubstances\Kabomu\MiscUtilsInternal;
use AaronicSubstances\Kabomu\Exceptions\KabomuIOException;

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
    public function testReading(int $contentLength, ?array $initialData, string $srcData,
            string $expected) {
        // arrange
        $stream = self::createRandomizedReadInputStream(
            MiscUtilsInternal::stringToBytes($srcData));
        if ($initialData !== null) {
            $initialData = array_map(function($item) { return MiscUtilsInternal::stringToBytes($item); },
                $initialData);
        }
        $instance = TlvUtils::createContentLengthEnforcingStream(
            $stream, $contentLength, $initialData);

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
            [0, null, '',     ''],
            [0, null, 'a',      ''],
            [1, null, 'ab',     "a"],
            [2, null, 'ab',     "ab"],
            [2, null, 'abc',    "ab"],
            [3, null, 'abc',    "abc"],
            [4, null, 'abcd',   "abcd"],
            [5, null, 'abcde',  "abcde"],
            [6, null, 'abcdefghi', 'abcdef'],
            // test initialData
            [0, [''], 'a',      ''],
            [0, ['a'], 'b',      ''],
            [2, ['a'], 'b',     "ab"],
            [2, ['a', 'bc'], 'd',     "ab"],
            [6, ['ab', '', 'c', 'de'], 'fghi', 'abcdef'],
            [7, ['0123xyz'], 'a', '0123xyz'],
            [10, ['ab'], 'cdefghij', 'abcdefghij'],
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