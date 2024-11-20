<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

use Amp\PHPUnit\AsyncTestCase;

use AaronicSubstances\Kabomu\Exceptions\KabomuIOException;

class IOUtilsInternalTest extends AsyncTestCase  {

    public function testReadBytesFully() {
        // arrange
        $reader = createRandomizedReadInputStream("\x00\x01\x02\x03\x04\x05\x06\x07");

        // act
        $data = IOUtilsInternal::readBytesFully($reader, 3);

        // assert
        $this->assertSame(bin2hex("\x00\x01\x02"), bin2hex($data));
        
        // assert that zero length reading doesn't cause problems.
        $data =  IOUtilsInternal::readBytesFully($reader, 0);
        $this->assertSame("", bin2hex($data));

        // act again
        $data = IOUtilsInternal::readBytesFully($reader, 3);
        
        // assert
        $this->assertSame(bin2hex("\x03\x04\x05"), bin2hex($data));
        
        // act again
        $data = IOUtilsInternal::readBytesFully($reader, 2);
        
        // assert
        $this->assertSame(bin2hex("\x06\x07"), bin2hex($data));

        // test zero byte reads.
        $data =  IOUtilsInternal::readBytesFully($reader, 0);
        $this->assertSame("", bin2hex($data));
    }

    public function testReadBytesFullyForErrors() {
        // arrange
        $reader = createUnreadEnabledReadableBuffer("\x00\x01\x02\x03\x04\x05\x06\x07");

        // act
        $data = IOUtilsInternal::readBytesFully($reader, 5);

        // assert
        $this->assertSame(bin2hex("\x00\x01\x02\x03\x04"), bin2hex($data));

        // arrange for unexpected end of read
        $this->expectException(KabomuIOException::class);
        $this->expectExceptionMessage("end of read");

        // act and assert
        IOUtilsInternal::readBytesFully($reader, 5);
    }

    /**
     * @dataProvider createTestCopyData
    */
    public function testCopy(string $srcData) {
        // arrange
        $expected = MiscUtilsInternal::stringToBytes($srcData);
        $readerStream = createRandomizedReadInputStream($expected);
        $writerStream = new WritableBuffer2();

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