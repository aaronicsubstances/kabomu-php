<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

use PHPUnit\Framework\TestCase;

class MiscUtilsInternalTest extends TestCase {

    /**
     * @dataProvider createTestSerializeInt32BEData
    */
    public function testSerializeInt32BE(int $v, string $expected): void {
        $actual = MiscUtilsInternal::serializeInt32BE($v);
        $this->assertEquals(bin2hex($expected), bin2hex($actual));
    }

    public static function createTestSerializeInt32BEData(): array {
        return [
            [2001, "\x00\x00\x07\xd1"],
            [-10_999, "\xff\xff\xd5\x09"],
            [1_000_000, "\x00\x0f\x42\x40"],
            [1_000_000_000, "\x3b\x9a\xca\x00"],
            [-1_000_000_000, "\xc4\x65\x36\x00"]
        ];
    }
    
    /**
     * @dataProvider createTestDeserializeInt32BEData
    */
    public function testDeserializeInt32BE(string $data, int $offset, int $expected) {
        $actual = MiscUtilsInternal::deserializeInt32BE($data, $offset);
        $this->assertEquals($expected, $actual);
    }

    public static function createTestDeserializeInt32BEData(): array {
        return [
            ["\x00\x00\x07\xd1", 0, 2001],
            ["\xff\xff\xd5\x9\x00", 0, -10_999],
            ["\x00\x0f\x42\x40", 0, 1_000_000, ],
            ["\xc4\x65\x36\x00", 0, -1_000_000_000],
            ["\x08\x02\x88\xca\x6b\x9c\x01", 2, -2_000_000_100]
        ];
    }

    /**
     * @dataProvider createTestParseInt48Data
    */
    public function testParseInt48($input, int $expected) {
        $actual = MiscUtilsInternal::parseInt48($input);
        $this->assertSame($expected, $actual);
    }

    public static function createTestParseInt48Data(): array {
        return [
            ["0", 0],
            ["1", 1],
            ["2", 2],
            [" 20", 20],
            [" 200 ", 200],
            ["-1000", -1000],
            [1000000, 1_000_000],
            ["-1000000000", -1_000_000_000],
            ["4294967295", 4_294_967_295],
            ["-50000000000000", -50_000_000_000_000],
            ["100000000000000", 100_000_000_000_000],
            ["140737488355327", 140_737_488_355_327],
            ["-140737488355328", -140_737_488_355_328]
        ];
    }
    
    /**
     * @dataProvider createTestParsetInt48ForErrorsData
    */
    public function testParsetInt48ForErrors(string $input) {
        $this->expectException(\InvalidArgumentException::class);

        MiscUtilsInternal::parseInt48($input);
    }

    public static function createTestParsetInt48ForErrorsData() {
        return [
            [" "],
            ["false"],
            ["xyz"],
            ["1.23"],
            ["2.0"],
            ["140737488355328"],
            ["-140737488355329"],
            ["72057594037927935"]
        ];
    }

    /**
     * @dataProvider createTestParseInt32Data
    */
    public function testParseInt32($input, int $expected) {
        $actual = MiscUtilsInternal::parseInt32($input);
        $this->assertSame($expected, $actual);
    }

    public static function createTestParseInt32Data(): array {
        return [
            ["0", 0],
            ["1", 1],
            ["2", 2],
            [" 20", 20],
            [" 200 ", 200],
            ["-1000", -1000],
            [1000000, 1_000_000],
            ["-1000000000", -1_000_000_000],
            ["2147483647", 2_147_483_647],
            ["-2147483648", -2_147_483_648],
            // remainder are verifications
            [2_147_483_647, 2_147_483_647],
            [-2_147_483_648, -2_147_483_648]
        ];
    }

    /**
     * @dataProvider createTestParsetInt32ForErrorsData
    */
    public function testParsetInt32ForErrors(string $input) {
        $this->expectException(\InvalidArgumentException::class);

        MiscUtilsInternal::parseInt32($input);
    }

    public static function createTestParsetInt32ForErrorsData() {
        return [
            [""],
            [" "],
            ["false"],
            ["xyz"],
            ["1.23"],
            ["2.0"],
            ["2147483648"],
            ["-2147483649"],
            ["50000000000000"]
        ];
    }

    public function testStringToBytes() {
        $actual = MiscUtilsInternal::stringToBytes("");
        $this->assertEquals("", $actual);

        $actual = MiscUtilsInternal::stringToBytes("abc");
        $this->assertEquals("abc", $actual);

        $expected = [0x46, 0x6f, 0x6f, 0x20, 0xc2, 0xa9, 0x20, 0x62, 0x61, 0x72, 0x20,
            0xf0, 0x9d, 0x8c, 0x86, 0x20, 0x62, 0x61, 0x7a, 0x20, 0xe2, 0x98, 0x83,
            0x20, 0x71, 0x75, 0x78];
        $expected = implode(array_map(function($item) { return chr($item); }, $expected));
        $actual = MiscUtilsInternal::stringToBytes("Foo \u{00a9} bar \u{0001d306} baz \u{2603} qux");
        $this->assertEquals(bin2hex($expected), bin2hex($actual));
    }

    public function testBytesToString() {
        $expected = "";
        $actual = MiscUtilsInternal::bytesToString("");
        $this->assertSame($expected, $actual);

        $expected = "abc";
        $actual = MiscUtilsInternal::bytesToString("abc");
        $this->assertSame($expected, $actual);
    
        $expected = "Foo \u{00a9} bar \u{0001d306} baz \u{2603} qux";
        $input = [
            0x46, 0x6f, 0x6f, 0x20, 0xc2, 0xa9, 0x20,
            0x62, 0x61, 0x72, 0x20,
            0xf0, 0x9d, 0x8c, 0x86, 0x20, 0x62, 0x61,
            0x7a, 0x20, 0xe2, 0x98, 0x83,
            0x20, 0x71, 0x75, 0x78
        ];
        $input = implode(array_map(function($item) { return chr($item); }, $input));
        $actual = MiscUtilsInternal::bytesToString($input);
        $this->assertSame($expected, $actual);
    }
}