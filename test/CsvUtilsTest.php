<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

use PHPUnit\Framework\TestCase;

class CsvUtilsTest extends TestCase {

    /**
     * @dataProvider createTestEscapeValueData
    */
    public function testEscapeValue(string $raw, string $expected): void {
        $actual = CsvUtils::escapeValue($raw);
        $this->assertSame($expected, $actual);
    }

    public static function createTestEscapeValueData(): array {
        return [
            ["", '""'],
            ["0", "0"],
            ["d", "d"],
            ["\n", "\"\n\""],
            ["\r", "\"\r\""],
            ["m,n", "\"m,n\""],
            ["m\"n", "\"m\"\"n\""],
        ];
    }

    /**
     * @dataProvider createTestUnescapeValueData
    */
    public function testUnescapeValue(string $escaped, string $expected): void {
        $actual = CsvUtils::unescapeValue($escaped);
        $this->assertSame($expected, $actual);
    }

    public static function createTestUnescapeValueData(): array {
        return [
            ['""', ""],
            ["d", "d"],
            ["\"\n\"", "\n"],
            ["\"\r\"", "\r"],
            ["\"m,n\"", "m,n"],
            ["\"m\"\"n\"", "m\"n"]
        ];
    }

    /**
     * @dataProvider createTestUnescapeValueForErrorsData
    */
    public function testUnescapeValueForErrors(string $escaped) {
        $this->expectException(\InvalidArgumentException::class);

        CsvUtils::unescapeValue($escaped);
    }

    public static function createTestUnescapeValueForErrorsData(): array {
        return [
            ["\""],
            ["d\""],
            ["\"\"\""],
            [","],
            ["m,n\n"],
            ["\"m\"n"]
        ];
    }

    /**
     * @dataProvider createTestSerializeData
    */
    public function testSerialize(array $rows, string $expected) {
        $actual = CsvUtils::serialize($rows);
        $this->assertSame($expected, $actual);
    }

    public static function createTestSerializeData(): array {
        $testData = array();

        $rows = array();
        $expected = "";
        $testData[] = [$rows, $expected];

        $rows = [
            [""]
        ];
        $expected = "\"\"\n";
        $testData[] = [$rows, $expected];

        $rows = [
            array()
        ];
        $expected = "\n";
        $testData[] = [$rows, $expected];

        $rows = [
            ["a"],
            ["b", "c"]
        ];
        $expected = "a\nb,c\n";
        $testData[] = [$rows, $expected];

        $rows = [
            array(),
            array(",", "c")
        ];
        $expected = "\n\",\",c\n";
        $testData[] = [$rows, $expected];

        $rows = array(
            array("head", "tail", "."),
            array("\n", " c\"d "),
            array()
        );
        $expected = "head,tail,.\n\"\n\",\" c\"\"d \"\n\n";
        $testData[] = [$rows, $expected];

        $rows = array(
            array("a\nb,c\n"),
            array("\n\",\",c\n", "head,tail,.\n\"\n\",\" c\"\"d \"\n\n")
        );
        $expected = "\"a\nb,c\n\"\n" .
            "\"\n\"\",\"\",c\n\",\"head,tail,.\n\"\"\n\"\",\"\" c\"\"\"\"d \"\"\n\n\"\n";
        $testData[] = [$rows, $expected];

        // for case detected in encodeQuasiHttpHeaders
        $rows = [
            ["", "", "", "0"],
        ];
        $expected = '"","","",0'. "\n";
        $testData[] = [$rows, $expected];

        return $testData;
    }

    /**
     * @dataProvider createTestDeserializeData
    */
    public function testDeserialize(string $csv, array $expected): void {
        $actual = CsvUtils::deserialize($csv);
        $this->assertEquals($expected, $actual);
    }

    public static function createTestDeserializeData(): array {
        $testData = array();

        $csv = "";
        $expected = array();
        $testData[] = [$csv, $expected];
        
        $csv = "\"\"";
        $expected = [
            [""]
        ];
        $testData[] = [$csv, $expected];
        
        $csv = "\n";
        $expected = [
            []
        ];
        $testData[] = [$csv, $expected];

        $csv = "\"\",\"\"\n";
        $expected = [
            ["", ""]
        ];
        $testData[] = [$csv, $expected];

        $csv = "\"\",\"\"";
        $expected = [
            ["", ""]
        ];
        $testData[] = [$csv, $expected];

        $csv = "a\nb,c\n";
        $expected = [
            ["a"],
            ["b", "c"]
        ];
        $testData[] = [$csv, $expected];

        $csv = "a\nb,c";
        $expected = [
            ["a"],
            ["b", "c"]
        ];
        $testData[] = [$csv, $expected];

        $csv = "a,\"\"\nb,c";
        $expected = [
            ["a", ""],
            ["b", "c"]
        ];
        $testData[] = [$csv, $expected];

        $csv = "a\nb,";
        $expected = [
            ["a"],
            ["b", ""]
        ];
        $testData[] = [$csv, $expected];

        $csv = "\"a\"\n\"b\",\"\""; // test for unnecessary quotes
        $expected = [
            ["a"],
            ["b", ""]
        ];
        $testData[] = [$csv, $expected];

        $csv = "\r\n\",\",c\r\n";
        $expected = [
            [],
            [",", "c"]
        ];
        $testData[] = [$csv, $expected];

        $csv = "\n\",\",c";
        $expected = [
            [],
            [",", "c"]
        ];
        $testData[] = [$csv, $expected];

        $csv = "head,tail,.\n\"\n\",\" c\"\"d \"\n\n";
        $expected = [
            ["head", "tail", "."],
            ["\n", " c\"d "],
            []
        ];
        $testData[] = [$csv, $expected];

        $csv = "head,tail,.\n\"\n\",\" c\"\"d \"\n";
        $expected = [
            ["head", "tail", "."],
            ["\n", " c\"d "]
        ];
        $testData[] = [$csv, $expected];

        $csv = "head,tail,.\n\"\r\n\",\" c\"\"d \"\r";
        $expected = [
            ["head", "tail", "."],
            ["\r\n", " c\"d "]
        ];
        $testData[] = [$csv, $expected];

        $csv = "\"a\nb,c\n\"\n" .
            "\"\n\"\",\"\",c\n\",\"head,tail,.\n\"\"\n\"\",\"\" c\"\"\"\"d \"\"\n\n\"\n";
        $expected = [
            ["a\nb,c\n"],
            ["\n\",\",c\n", "head,tail,.\n\"\n\",\" c\"\"d \"\n\n"]
        ];
        $testData[] = [$csv, $expected];

        return $testData;
    }

    /**
     * @dataProvider createTestDeserializeForErrorsData
    */
    public function testDeserializeForErrors(string $csv) {
        $this->expectException(\InvalidArgumentException::class);

        CsvUtils::deserialize($csv);
    }

    public static function createTestDeserializeForErrorsData() {
        return [
            array("\""),
            array("\"1\"2"),
            array("1\"\"2\""),
            array("1,2\",3")
        ];
    }
}