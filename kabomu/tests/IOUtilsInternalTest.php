<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

use Amp\Future;
use Amp\ByteStream\ReadableBuffer;
use Amp\ByteStream\WritableBuffer;
use Amp\PHPUnit\AsyncTestCase;

use AaronicSubstances\Kabomu\Abstractions\PushbackReadableStream;
use AaronicSubstances\Kabomu\Exceptions\KabomuIOException;

class IOUtilsInternalTest extends AsyncTestCase  {

    public static function readAllBytes($stream) {
        return \Amp\ByteStream\buffer($stream);
    }

    public static function defer() {
        $task = \Amp\async(function () { });
        Future\await([$task]);
    }

    public static function createRandomizedReadInputStream($data) {
        $inputStream = new \Amp\ByteStream\ReadableIterableStream((function () use (&$data) {
            self::defer();
            $offset = 0;
            while ($offset < strlen($data)) {
                //$bytesToCopy = 1;
                $bytesToCopy = rand(1, strlen($data) - $offset);
                yield substr($data, $offset, $bytesToCopy);
                $offset += $bytesToCopy;
                self::defer();
            }
        })());
        return new PushbackReadableStream($inputStream);
    }

    public function testReadBytesFully() {
        // arrange
        $reader = self::createRandomizedReadInputStream("\x00\x01\x02\x03\x04\x05\x06\x07");

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
        $reader = new PushbackReadableStream(new ReadableBuffer("\x00\x01\x02\x03\x04\x05\x06\x07"));

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
        $readerStream = self::createRandomizedReadInputStream($expected);
        $writerStream = new WritableBuffer();

        // act
        IOUtilsInternal::copy($readerStream, $writerStream);

        // assert
        $rc = new \ReflectionClass($writerStream);
        $prop = $rc->getProperty('contents');
        $prop->setAccessible(true);
        $actual = $prop->getValue($writerStream);
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