<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Tlv;

use Amp\PHPUnit\AsyncTestCase;

use AaronicSubstances\Kabomu\Exceptions\KabomuIOException;

class ContentLengthEnforcingStreamInternalTest extends AsyncTestCase {

    /**
     * @dataProvider createTestReadingData
    */
    public function testReading(int $contentLength, ?string $initialData, string $srcData,
            string $expected) {
        // arrange
        $stream = \AaronicSubstances\Kabomu\createRandomizedReadableBuffer($srcData);
        $instance = TlvUtils::createContentLengthEnforcingStream(
            $stream, $contentLength, $initialData);

        // act
        $actual = \AaronicSubstances\Kabomu\readAllBytes($instance);

        // assert
        $this->assertSame($expected, $actual);

        // assert non-repeatability.
        $actual = \AaronicSubstances\Kabomu\readAllBytes($instance);
        $this->assertSame("", $actual);
    }

    public static function createTestReadingData() {
        return [
            [0, null, '',     ''],
            [2, null, 'ab',     "ab"],
            [3, null, 'abc',    "abc"],
            [4, null, 'abcd',   "abcd"],
            [5, null, 'abcde',  "abcde"],
            // test initialData
            [2, 'a', 'b',     "ab"],
            [10, 'ab', 'cdefghij', 'abcdefghij'],
        ];
    }
    
    /**
     * @dataProvider createTestReadingWithLeftOversData
    */
    public function testReadingWithLeftOvers(int $contentLength, ?string $initialData, string $srcData, string $expected, string $leftOver) {
        // arrange
        $stream = \AaronicSubstances\Kabomu\createRandomizedReadableBuffer($srcData);
        $stream = \AaronicSubstances\Kabomu\makeStreamUnreadEnabled($stream);
        $instance = TlvUtils::createContentLengthEnforcingStream(
            $stream, $contentLength, $initialData);

        // assert expected
        $actual = \AaronicSubstances\Kabomu\readAllBytes($instance);
        $this->assertSame($expected, $actual);

        // assert left over
        $actual = \AaronicSubstances\Kabomu\readAllBytes($stream);
        $this->assertSame($leftOver, $actual);
    }

    public static function createTestReadingWithLeftOversData() {
        return [
            [0, null, '',     '', ''],
            [0, null, 'a',      '', 'a'],
            [1, null, 'ab',     "a", "b"],
            [2, null, 'ab',     "ab", ""],
            [2, null, 'abc',    "ab", 'c'],
            [3, null, 'abc',    "abc", ''],
            [4, null, 'abcd',   "abcd", ''],
            [5, null, 'abcde',  "abcde", ''],
            [6, null, 'abcdefghi', 'abcdef', 'ghi'],
            // test initialData
            [0, '', 'a',      '', 'a'],
            [0, 'a', 'b',      '', 'ab'],
            [2, 'a', 'b',     "ab", ""],
            [2, 'abc', 'd',     "ab", "cd"],
            [6, 'abcde', 'fghi', 'abcdef', "ghi"],
            [7, '0123xyz', 'a', '0123xyz', "a"],
            [10, 'ab', 'cdefghij', 'abcdefghij', ""],
        ];
    }

    /**
     * @dataProvider createTestReadingForErrorsData
    */
    public function testReadingForErrors(int $contentLength, string $srcData) {
        // arrange
        $stream =  \AaronicSubstances\Kabomu\createReadableBuffer($srcData);
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
}