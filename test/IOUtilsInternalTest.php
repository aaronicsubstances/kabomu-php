<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

use Amp\PHPUnit\AsyncTestCase;

use AaronicSubstances\Kabomu\Exceptions\KabomuIOException;

class IOUtilsInternalTest extends AsyncTestCase  {

    public function testReadBytesAtLeast() {
        // arrange
        $reader = createRandomizedReadableBuffer("\x00\x01\x02\x03\x04\x05\x06\x07");
        $leftOver = [];

        // act
        $data = IOUtilsInternal::readBytesAtLeast($reader, $leftOver, 3);

        // assert
        $this->assertSame(bin2hex("\x00\x01\x02"), bin2hex($data));
        
        // assert that zero length reading doesn't cause problems.
        $data =  IOUtilsInternal::readBytesAtLeast($reader, $leftOver, 0);
        $this->assertEmpty($data);

        // act again
        $data = IOUtilsInternal::readBytesAtLeast($reader, $leftOver, 3);
        
        // assert
        $this->assertSame(bin2hex("\x03\x04\x05"), bin2hex($data));
        
        // act again
        $data = IOUtilsInternal::readBytesAtLeast($reader, $leftOver, 2);
        
        // assert
        $this->assertSame(bin2hex("\x06\x07"), bin2hex($data));

        // assert that if we are done reading, there should be no chunk
        // inside left over.
        $this->assertEmpty($leftOver);

        // test zero byte reads after end of read.
        $data =  IOUtilsInternal::readBytesAtLeast($reader, $leftOver, 0);
        $this->assertEmpty($data);
        $this->assertEmpty($leftOver);
    }

    public function testReadBytesAtLeastForErrors() {
        // arrange
        $reader = createRandomizedReadableBuffer("\x00\x01\x02\x03\x04\x05\x06\x07");

        // arrange for unexpected end of read
        $this->expectException(KabomuIOException::class);
        $this->expectExceptionMessage("end of read");

        // act and assert
        $leftOver = [];
        $data = IOUtilsInternal::readBytesAtLeast($reader, $leftOver, 10);
    }

    /**
     * @dataProvider createTestCopyData
    */
    public function testCopy(string $srcData) {
        // arrange
        $expected = MiscUtilsInternal::stringToBytes($srcData);
        $readerStream = createRandomizedReadableBuffer($expected);
        $writerStream = createWritableBuffer();

        // act
        IOUtilsInternal::copy($readerStream, $writerStream);
        $actual = $writerStream->getContentsNow();

        // assert
        $this->assertSame(bin2hex($expected), bin2hex($actual));
    }

    public static function createTestCopyData() {
        return [
            [""],
            ["ab"],
            ["xyz"],
            ["abcdefghi"]
        ];
    }

}