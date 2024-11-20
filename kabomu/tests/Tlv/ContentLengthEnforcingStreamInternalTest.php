<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Tlv;

use Amp\ByteStream\ReadableBuffer;
use Amp\PHPUnit\AsyncTestCase;

use AaronicSubstances\Kabomu\IOUtilsInternal;
use AaronicSubstances\Kabomu\MiscUtilsInternal;
use AaronicSubstances\Kabomu\Exceptions\KabomuIOException;

class ContentLengthEnforcingStreamInternalTest extends AsyncTestCase {

    /**
     * @dataProvider createTestReadingData
    */
    public function testReading(int $contentLength, ?array $initialData, string $srcData,
            string $expected) {
        // arrange
        $stream = \AaronicSubstances\Kabomu\createRandomizedReadInputStream(
            MiscUtilsInternal::stringToBytes($srcData));
        if ($initialData !== null) {
            $initialData = array_map(function($item) { return MiscUtilsInternal::stringToBytes($item); },
                $initialData);
        }
        $instance = TlvUtils::createContentLengthEnforcingStream(
            $stream, $contentLength, $initialData);

        // act
        $actual = MiscUtilsInternal::bytesToString(\AaronicSubstances\Kabomu\readAllBytes($instance));

        // assert
        $this->assertSame($expected, $actual);

        // assert non-repeatability.
        $actual = MiscUtilsInternal::bytesToString(\AaronicSubstances\Kabomu\readAllBytes($instance));
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
        \AaronicSubstances\Kabomu\readAllBytes($instance);
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
        $stream = \AaronicSubstances\Kabomu\createUnreadEnabledReadableBuffer("\x00\x01\x02\x04");
        $instance = TlvUtils::createContentLengthEnforcingStream($stream, 3);

        $actual = IOUtilsInternal::readBytesFully($instance, 0);
        $this->assertEmpty($actual);

        $actual = IOUtilsInternal::readBytesFully($instance, 3);
        $this->assertSame("000102", bin2hex($actual));

        $actual = IOUtilsInternal::readBytesFully($instance, 0);
        $this->assertEmpty($actual);

        // test aftermath reads
        $actual = \AaronicSubstances\Kabomu\readAllBytes($stream);
        $this->assertSame("04", bin2hex($actual));
    }
}