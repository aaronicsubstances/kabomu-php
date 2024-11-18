<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MiscUtilsInternalTest extends TestCase {

    #[DataProvider('createTestSerializeInt32BEData')]
    public function testSerializeInt32BE(int $v, array $expected): void {
        $actual = MiscUtilsInternal::serializeInt32BE($v);
        $this->assertEquals($expected, $actual);
    }

    public static function createTestSerializeInt32BEData(): array {
        return [
            [2001, [0, 0, 7, 0xd1]],
            [-10_999, [0xff, 0xff, 0xd5, 9]],
            [1_000_000, [0, 0xf, 0x42, 0x40]],
            [1_000_000_000, [0x3b, 0x9a, 0xca, 0]],
            [-1_000_000_000, [0xc4, 0x65, 0x36, 0]]
        ];
    }

    #[DataProvider('createTestDeserializeInt32BEData')]
    public function testDeserializeInt32BE(array $rawBytes, int $offset, int $expected) {
        $actual = MiscUtilsInternal::deserializeInt32BE($rawBytes, $offset);
        $this->assertEquals($expected, $actual);
    }

    public static function createTestDeserializeInt32BEData(): array {
        return [
            [[0, 0, 7, 0xd1], 0, 2001],
            [[0xff, 0xff, 0xd5, 9], 0, -10_999],
            [[0, 0xf, 0x42, 0x40], 0, 1_000_000, ],
            [[0xc4, 0x65, 0x36, 0], 0, -1_000_000_000],
            [[8, 2, 0x88, 0xca, 0x6b, 0x9c, 1], 2, -2_000_000_100]
        ];
    }

    #[DataProvider('createTestParseInt48Data')]
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

    #[DataProvider('createTestParsetInt48ForErrorsData')]
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

    #[DataProvider('createTestParseInt32Data')]
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

    #[DataProvider('createTestParsetInt32ForErrorsData')]
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
        $this->assertEquals([], $actual);

        $actual = MiscUtilsInternal::stringToBytes("abc");
        $this->assertEquals([ord('a'), ord('b'), ord('c')], $actual);

        $actual = MiscUtilsInternal::stringToBytes("Foo \u{00a9} bar \u{0001d306} baz \u{2603} qux");
        $this->assertEquals([0x46, 0x6f, 0x6f, 0x20, 0xc2, 0xa9, 0x20, 0x62, 0x61, 0x72, 0x20,
            0xf0, 0x9d, 0x8c, 0x86, 0x20, 0x62, 0x61, 0x7a, 0x20, 0xe2, 0x98, 0x83,
            0x20, 0x71, 0x75, 0x78], $actual);
    }

    public function testBytesToString() {
        $expected = "";
        $actual = MiscUtilsInternal::bytesToString([]);
        $this->assertSame($expected, $actual);

        $expected = "abc";
        $actual = MiscUtilsInternal::bytesToString([97, 98, 99]);
        $this->assertSame($expected, $actual);
    
        $expected = "Foo \u{00a9} bar \u{0001d306} baz \u{2603} qux";
        $actual = MiscUtilsInternal::bytesToString([
            0x46, 0x6f, 0x6f, 0x20, 0xc2, 0xa9, 0x20,
            0x62, 0x61, 0x72, 0x20,
            0xf0, 0x9d, 0x8c, 0x86, 0x20, 0x62, 0x61,
            0x7a, 0x20, 0xe2, 0x98, 0x83,
            0x20, 0x71, 0x75, 0x78
        ]);
        $this->assertSame($expected, $actual);
    }
}