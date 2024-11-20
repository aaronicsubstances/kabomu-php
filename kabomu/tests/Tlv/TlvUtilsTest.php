<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Tlv;

use Amp\ByteStream\ReadableBuffer;
use Amp\PHPUnit\AsyncTestCase;

use PHPUnit\Framework\TestCase;

class TlvUtilsTest extends AsyncTestCase {

    public static function readAllBytes($stream) {
        return \Amp\ByteStream\buffer($stream);
    }

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

    public function testCreateTlvEncodingReadableStream() {
        $backingStream = new ReadableBuffer("\x2c");
        $expected = "\x00\x00\x00\x10" .
            "\x00\x00\x00\x01" .
            "\x2c" .
            "\x00\x00\x00\x10" .
            "\x00\x00\x00\x00";
        $instance = TlvUtils::createTlvEncodingReadableStream($backingStream, 0x10);
        $actual = self::readAllBytes($instance);
        $this->assertSame(bin2hex($expected), bin2hex($actual));
    }
}