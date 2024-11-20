<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Tlv;

use Amp\ByteStream\ReadableBuffer;
use Amp\PHPUnit\AsyncTestCase;

use AaronicSubstances\Kabomu\IOUtilsInternal;
use AaronicSubstances\Kabomu\MiscUtilsInternal;
use AaronicSubstances\Kabomu\Exceptions\KabomuIOException;

class BodyChunkDecodingStreamInternalTest extends AsyncTestCase {

    /**
     * @dataProvider createTestReadingData
    */
    public function testReading($srcData, $expectedTag, $tagToIgnore, $expected) {
        // arrange
        $stream = \AaronicSubstances\Kabomu\createRandomizedReadInputStream($srcData, false);
        $instance = TlvUtils::createTlvDecodingReadableStream(
            $stream, $expectedTag, $tagToIgnore);

        // act
        $actual = \AaronicSubstances\Kabomu\readAllBytes($instance);

        $this->assertSame(bin2hex($expected), bin2hex($actual));
    }

    public static function createTestReadingData() {
        return [
            [
                "\x00\x00\x00\x89" .
                    "\x00\x00\x00\x00", 
                0x89, 5, ""
            ],
            [
                "\x00\x00\x00\x15" .
                    "\x00\x00\x00\x02" .
                    "\x02\x03" .
                    "\x00\x00\x00\x08" .
                    "\x00\x00\x00\x00",
                0x08, 0x15, ""
            ],
            [
                "\x00\x00\x00\x08" .
                    "\x00\x00\x00\x02" .
                    "\x02\x03" .
                    "\x00\x00\x00\x08" .
                    "\x00\x00\x00\x00",
                0x08, 0x15, "\x02\x03"
            ],
            [
                "\x00\x00\x00\x08" .
                    "\x00\x00\x00\x01" .
                    "\x02" .
                    "\x00\x00\x00\x08" .
                    "\x00\x00\x00\x01" .
                    "\x03" .
                    "\x00\x00\x00\x08" .
                    "\x00\x00\x00\x00",
                0x08, 0x15, "\x02\x03"
            ],
            [
                "\x00\x00\x3d\x15" .
                    "\x00\x00\x00\x00" .
                    "\x30\xa3\xb5\x17" .
                    "\x00\x00\x00\x01" .
                    "\x02" .
                    "\x00\x00\x3d\x15" .
                    "\x00\x00\x00\x07" .
                    "\x00\x00\x00\x00\x00\x00\x00" .
                    "\x30\xa3\xb5\x17" .
                    "\x00\x00\x00\x01" .
                    "\x03" .
                    "\x00\x00\x3d\x15" .
                    "\x00\x00\x00\x00" .
                    "\x30\xa3\xb5\x17" .
                    "\x00\x00\x00\x04" .
                    "\x02\x03\x45\x62" .
                    "\x00\x00\x3d\x15" .
                    "\x00\x00\x00\x01" .
                    "\x01" .
                    "\x30\xa3\xb5\x17" .
                    "\x00\x00\x00\x08" .
                    "\x91\x10\x02\x03\x45\x62\x70\x87" .
                    "\x30\xa3\xb5\x17" .
                    "\x00\x00\x00\x00",
                0x30a3b517, 0x3d15,
                "\x02\x03\x02\x03\x45\x62\x91\x10" .
                    "\x02\x03\x45\x62\x70\x87"
            ]
        ];
    }

    /**
     * @dataProvider createTestReadingWithCarryOversData
    */
    public function testReadingWithCarryOvers($carryOvers, $srcData, $expectedTag, $tagToIgnore, $expected) {
        // arrange
        $stream = \AaronicSubstances\Kabomu\createRandomizedReadInputStream($srcData, false);
        $instance = TlvUtils::createTlvDecodingReadableStream(
            $stream, $expectedTag, $tagToIgnore, $carryOvers);

        // act
        $actual = \AaronicSubstances\Kabomu\readAllBytes($instance);

        $this->assertSame(bin2hex($expected), bin2hex($actual));

        $this->assertEmpty(\AaronicSubstances\Kabomu\readAllBytes($stream));
    }

    public static function createTestReadingWithCarryOversData() {
        return [
            [
                null,
                "\x00\x00\x00\x89" .
                    "\x00\x00\x00\x00", 
                0x89, 5, ""
            ],
            [
                ["\x00\x00\x00"],
                "\x15" .
                    "\x00\x00\x00\x02" .
                    "\x02\x03" .
                    "\x00\x00\x00\x08" .
                    "\x00\x00\x00\x00",
                0x08, 0x15, ""
            ],
            [
                ["\x00\x00\x00\x08" .
                    "\x00\x00\x00\x02" .
                    "\x02", 
                "\x03" .
                    "\x00\x00\x00\x08" .
                    "\x00\x00\x00\x00"],
                "",
                0x08, 0x15, "\x02\x03"
            ],
            [
                [],
                "\x00\x00\x00\x08" .
                    "\x00\x00\x00\x01" .
                    "\x02" .
                    "\x00\x00\x00\x08" .
                    "\x00\x00\x00\x01" .
                    "\x03" .
                    "\x00\x00\x00\x08" .
                    "\x00\x00\x00\x00",
                0x08, 0x15, "\x02\x03"
            ],
            [
                null,
                "\x00\x00\x3d\x15" .
                    "\x00\x00\x00\x00" .
                    "\x30\xa3\xb5\x17" .
                    "\x00\x00\x00\x01" .
                    "\x02" .
                    "\x00\x00\x3d\x15" .
                    "\x00\x00\x00\x07" .
                    "\x00\x00\x00\x00\x00\x00\x00" .
                    "\x30\xa3\xb5\x17" .
                    "\x00\x00\x00\x01" .
                    "\x03" .
                    "\x00\x00\x3d\x15" .
                    "\x00\x00\x00\x00" .
                    "\x30\xa3\xb5\x17" .
                    "\x00\x00\x00\x04" .
                    "\x02\x03\x45\x62" .
                    "\x00\x00\x3d\x15" .
                    "\x00\x00\x00\x01" .
                    "\x01" .
                    "\x30\xa3\xb5\x17" .
                    "\x00\x00\x00\x08" .
                    "\x91\x10\x02\x03\x45\x62\x70\x87" .
                    "\x30\xa3\xb5\x17" .
                    "\x00\x00\x00\x00",
                0x30a3b517, 0x3d15,
                "\x02\x03\x02\x03\x45\x62\x91\x10" .
                    "\x02\x03\x45\x62\x70\x87"
            ]
        ];
    }

    /**
     * @dataProvider createTestReadingWithLeftOversData
    */
    public function testReadingWithLeftOvers($srcData, $expected, $leftOver) {
        // arrange
        $stream = \AaronicSubstances\Kabomu\createUnreadEnabledReadableBuffer($srcData);
        $instance = TlvUtils::createTlvDecodingReadableStream(
            $stream, 1);

        // assert expected
        $actual = \AaronicSubstances\Kabomu\readAllBytes($instance);
        $actual = MiscUtilsInternal::bytesToString($actual);
        $this->assertSame($expected, $actual);

        // assert left over
        $actual = \AaronicSubstances\Kabomu\readAllBytes($stream);
        $actual = MiscUtilsInternal::bytesToString($actual);
        $this->assertSame($leftOver, $actual);
    }

    public static function createTestReadingWithLeftOversData() {
        return [
            [
                "\x00\x00\x00\x01" .
                    "\x00\x00\x00\x00" .
                    "sea blue",
                "", "sea blue"
            ],
            [
                "\x00\x00\x00\x01" .
                    "\x00\x00\x00\x01" .
                    "a" .
                    "\x00\x00\x00\x01" .
                    "\x00\x00\x00\x00",
                "a", ""
            ],
            [
                "\x00\x00\x00\x01" .
                    "\x00\x00\x00\x03" .
                    "abc" .
                    "\x00\x00\x00\x01" .
                    "\x00\x00\x00\x01" .
                    "d" .
                    "\x00\x00\x00\x01" .
                    "\x00\x00\x00\x00" .
                    "xyz\n",
                "abcd", "xyz\n"
            ],
            [
                "\x00\x00\x00\x01" .
                    "\x00\x00\x00\x01" .
                    "a" .
                    "\x00\x00\x00\x01" .
                    "\x00\x00\x00\x07" .
                    "bcdefgh" .
                    "\x00\x00\x00\x01" .
                    "\x00\x00\x00\x01" .
                    "i" .
                    "\x00\x00\x00\x01" .
                    "\x00\x00\x00\x00" .
                    "-done with extra\nthat's it",
                "abcdefghi", "-done with extra\nthat's it"
            ]
        ];
    }

    /**
     * @dataProvider createTestDecodingForErrorsData
    */
    public function testDecodingForErrors($srcData, $expectedTag, $tagToIgnore, $expectedError) {
        $instance = new ReadableBuffer($srcData);
        $instance = TlvUtils::createTlvDecodingReadableStream(
            $instance, $expectedTag, $tagToIgnore);

        $this->expectException(KabomuIOException::class);
        $this->expectExceptionMessage($expectedError);

        \AaronicSubstances\Kabomu\readAllBytes($instance);
    }

    public static function createTestDecodingForErrorsData() {
        return [
            [
                "\x00\x00\x09\x00" .
                    "\x00\x00\x00\x12",
                0x0900, 0, "unexpected end of read"
            ],
            [
                "\x00\x00\x09\x00" .
                    "\x00\x00\x00\x12",
                10, 30, "unexpected tag"
            ],
            [
                "\x00\x00\x00\x00" .
                    "\x00\xff\xff\xec" .
                    "\x02\x03" .
                    "\x00\x00\x00\x14" .
                    "\x00\x00\x00\x00" .
                    "\x02\x03" .
                    "\x00\x00\x00\x08" .
                    "\x00\x00\x00\x00",
                0x14, 8, "invalid tag: 0"
            ],
            [
                "\x00\x00\x00\x14" .
                    "\xff\xff\xff\xec" .
                    "\x02\x03" .
                    "\x00\x00\x00\x14" .
                    "\x00\x00\x00\x00" .
                    "\x02\x03" .
                    "\x00\x00\x00\x00" .
                    "\x00\x00\x00\x00",
                0x14, 15, "invalid tag value length: -20"
            ]
        ];
    }
}