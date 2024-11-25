<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

use Amp\PHPUnit\AsyncTestCase;

class PushbackReadableStreamTest extends AsyncTestCase {

    public function testReads() {
        $instance = makeStreamUnreadEnabled(createReadableBuffer("\x00\x01\x02\x04"));

        $actual = readBytesFully($instance, 0);
        $this->assertEmpty(bin2hex($actual));

        $actual = readBytesFully($instance, 3);
        $this->assertSame("000102", bin2hex($actual));

        $actual = readAllBytes($instance);
        $this->assertSame("04", bin2hex($actual));

        $actual = readBytesFully($instance, 0);
        $this->assertEmpty(bin2hex($actual));
    }
}