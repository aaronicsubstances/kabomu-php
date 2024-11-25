<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Tlv;

use Amp\PHPUnit\AsyncTestCase;

use AaronicSubstances\Kabomu\IOUtilsInternal;
use AaronicSubstances\Kabomu\MiscUtilsInternal;
use AaronicSubstances\Kabomu\Exceptions\KabomuIOException;

class MaxLengthEnforcingStreamInternalTest extends AsyncTestCase {

    /**
     * @dataProvider createTestReadingData
    */
    function testReading(int $maxLength, string $expected) {
        // arrange
        $stream = \AaronicSubstances\Kabomu\createRandomizedReadableBuffer(
            MiscUtilsInternal::stringToBytes($expected));
        $instance = TlvUtils::createMaxLengthEnforcingStream(
            $stream, $maxLength);

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
        $stream = \AaronicSubstances\Kabomu\createReadableBuffer(
            MiscUtilsInternal::stringToBytes($srcData));
        $instance = TlvUtils::createMaxLengthEnforcingStream(
            $stream, $maxLength);
        $this->expectException(KabomuIOException::class);
        $this->expectExceptionMessage("exceeds limit of $maxLength");

        // act and assert
        \AaronicSubstances\Kabomu\readAllBytes($instance);
    }

    public static function createTestReadingForErrorsData() {
        return [
            [1, "ab"],
            [2, "abc"],
            [3, "abcd"],
            [5, "abcdefxyz"]
        ];
    }
}