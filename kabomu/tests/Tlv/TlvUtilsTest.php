<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Tlv;

use Amp\ByteStream\ReadableBuffer;
use Amp\PHPUnit\AsyncTestCase;

use PHPUnit\Framework\TestCase;

use AaronicSubstances\Kabomu\MiscUtilsInternal;

class TlvUtilsTest extends AsyncTestCase {

    /**
     * @dataProvider createTestEncodeTagAndLengthData
    */
    public function testEncodeTagAndLength(int $tag, int $length, string $expected): void {
        $actual = TlvUtils::encodeTagAndLength($tag, $length);
        $this->assertSame(bin2hex($expected), bin2hex($actual));
    }

    public static function createTestEncodeTagAndLengthData(): array {
        return [
            [0x15c0, 2, "\x00\x00\x15\xc0\x00\x00\x00\x02"],
            [0x12342143, 0, "\x12\x34\x21\x43\x00\x00\x00\x00"],
            [1, 0x78cdef01, "\x00\x00\x00\x01\x78\xcd\xef\x01"],
        ];
    }

    /**
     * @dataProvider createTestEncodeTagAndLengthForErrorsData
    */
    public function testEncodeTagAndLengthForErrors(int $tag, int $length): void {
        $this->expectException(\InvalidArgumentException::class);

        TlvUtils::encodeTagAndLength($tag, $length);
    }

    public static function createTestEncodeTagAndLengthForErrorsData(): array {
        return [
            [0, 1],
            [-1, 1],
            [2, -1],
        ];
    }

    /**
     * @dataProvider createTestDecodeTagData
    */
    public function testDecodeTag($data, int $offset, int $expected) {
        $actual = TlvUtils::decodeTag($data, $offset);
        $this->assertSame($expected, $actual);
    }

    public static function createTestDecodeTagData() {
        return [
            [
                "\x00\x00\x00\x01",
                0,
                1
            ],
            [
                "\x03\x40\x89\x11",
                0,
                0x03408911
            ],
            [
                "\x01\x56\x10\x01\x20\x02",
                1,
                0x56100120
            ]
        ];
    }

    /**
     * @dataProvider createTestDecodeTagForErrorsData
    */
    public function testDecodeTagForErrors($data, int $offset) {
        // couldn't use $this->expectException(\Exception::class)
        // because uninitiaized string offset error was not being caught.

        try {
            TlvUtils::decodeTag($data, $offset);
            $this->fail("Expected exception or notice");
        } catch (\Exception $exception) {
            $this->addToAssertionCount(1);
        }
    }

    public static function createTestDecodeTagForErrorsData() {
        return [
            ["\x01\x01\x01", 0],
            ["\x00\x00\x00", 0],
            ["\x05\x01\xc8\x03\x00\x03", 2]
        ];
    }

    /**
     * @dataProvider createTestDecodeLengthData
    */
    public function testDecodeLength($data, int $offset, int $expected) {
        $actual = TlvUtils::decodeLength($data, $offset);

        $this->assertSame($expected, $actual);
    }

   public static function createTestDecodeLengthData() {
        return [
            [
                "\x00\x00\x00\x00",
                0,
                0
            ],
            [
                "\x03\x40\x89\x11",
                0,
                0x03408911
            ],
            [
                "\x01\x56\x10\x01\x20\x02",
                1,
                0x56100120
            ]
            ];
    }

    /**
     * @dataProvider createTestDecodeLengthForErrorsData
    */
    public function testDecodeLengthForErrors($data, $offset) {
        // couldn't use $this->expectException(\Exception::class)
        // because uninitiaized string offset error was not being caught.

        try {
            TlvUtils::decodeLength($data, $offset);
            $this->fail("Expected exception or notice");
        } catch (\Exception $exception) {
            $this->addToAssertionCount(1);
        }
    }

    public static function createTestDecodeLengthForErrorsData() {
        return [
            [ "\x01\x01\x01\x02", 0 ],
            [ "\x05\x01\xc8\x03\x00\x03", 2 ]
        ];
    }

    public function testCreateTlvEncodingReadableStream() {
        $backingStream = new ReadableBuffer("\x2c");
        $expected = "\x00\x00\x00\x10" .
            "\x00\x00\x00\x01" .
            "\x2c" .
            "\x00\x00\x00\x10" .
            "\x00\x00\x00\x00";
        $instance = TlvUtils::createTlvEncodingReadableStream($backingStream, 0x10);
        $actual = \AaronicSubstances\Kabomu\readAllBytes($instance);
        $this->assertSame(bin2hex($expected), bin2hex($actual));
    }

    /**
     * @dataProvider createTestBodyChunkCodecStreamsData
    */
    public function testBodyChunkCodecStreams(string $expected, int $tagToUse) {
        // arrange
        $instance = \AaronicSubstances\Kabomu\createRandomizedReadInputStream(
            MiscUtilsInternal::stringToBytes($expected));
        $instance = TlvUtils::createTlvEncodingReadableStream($instance, $tagToUse);
        $instance = TlvUtils::createTlvDecodingReadableStream($instance,
            $tagToUse);

        // act
        $actual = \AaronicSubstances\Kabomu\readAllBytes($instance);
        $actual = MiscUtilsInternal::bytesToString($actual);

        // assert
        $this->assertSame($expected, $actual);
    }

    public static function createTestBodyChunkCodecStreamsData() {
        return [
            ['', 1],
            ["a", 4],
            ["ab", 45],
            ["abc", 60],
            ["abcd", 120_000_000],
            ["abcde", 34_000_000],
            ["abcdefghi", 0x3245671d]
        ];
    }
}