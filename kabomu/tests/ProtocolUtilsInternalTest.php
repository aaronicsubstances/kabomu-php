<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

use Amp\PHPUnit\AsyncTestCase;

use AaronicSubstances\Kabomu\Abstractions\CustomTimeoutScheduler;
use AaronicSubstances\Kabomu\Abstractions\DefaultQuasiHttpResponse;
use AaronicSubstances\Kabomu\Abstractions\DefaultTimeoutResult;
use AaronicSubstances\Kabomu\Abstractions\TimeoutResult;
use AaronicSubstances\Kabomu\Exceptions\QuasiHttpException;

class ProtocolUtilsInternalTest extends AsyncTestCase {

    public function testRunTimeoutScheduler1() {
        $expected = new DefaultQuasiHttpResponse();
        $proc = fn() => $expected;
        $instance = new class implements CustomTimeoutScheduler {
            public function runUnderTimeout(\Closure $f): ?TimeoutResult {
                $result = $f();
                return new DefaultTimeoutResult(false, $result, null);
            }
        };
        $actual = ProtocolUtilsInternal::runTimeoutScheduler(
            $instance, true, $proc);
        $this->assertSame($expected, $actual);
    }

    public function testRunTimeoutScheduler2() {
        $expected = null;
        $proc = fn() => $expected;
        $instance = new class implements CustomTimeoutScheduler {
            public function runUnderTimeout(\Closure $f): ?TimeoutResult {
                $result = $f();
                return new DefaultTimeoutResult(false, $result, null);
            }
        };
        $actual = ProtocolUtilsInternal::runTimeoutScheduler(
            $instance, false, $proc);
        $this->assertSame($expected, $actual);
    }

    public function testRunTimeoutScheduler3() {
        $expected = null;
        $proc = fn() => $expected;
        $instance = new class implements CustomTimeoutScheduler {
            public function runUnderTimeout(\Closure $f): ?TimeoutResult {
                return null;
            }
        };
        $actual = ProtocolUtilsInternal::runTimeoutScheduler(
            $instance, false, $proc);
        $this->assertNull($actual);
    }

    public function testRunTimeoutScheduler4() {
        $expected = null;
        $proc = fn() => $expected;
        $instance = new class implements CustomTimeoutScheduler {
            public function runUnderTimeout(\Closure $f): ?TimeoutResult {
                return null;
            }
        };

        $this->expectException(QuasiHttpException::class);
        $this->expectExceptionMessage("no response from timeout scheduler");
        $this->expectExceptionCode(QuasiHttpException::REASON_CODE_GENERAL);
        
        ProtocolUtilsInternal::runTimeoutScheduler(
            $instance, true, $proc);
    }

    public function testRunTimeoutScheduler5() {
        $expected = null;
        $proc = fn() => $expected;
        $instance = new class implements CustomTimeoutScheduler {
            public function runUnderTimeout(\Closure $f): ?TimeoutResult {
                return new DefaultTimeoutResult(true, null, null);
            }
        };

        $this->expectException(QuasiHttpException::class);
        $this->expectExceptionMessage("send timeout");
        $this->expectExceptionCode(QuasiHttpException::REASON_CODE_TIMEOUT);
        
        ProtocolUtilsInternal::runTimeoutScheduler(
            $instance, true, $proc);
    }

    public function testRunTimeoutScheduler6() {
        $expected = null;
        $proc = fn() => $expected;
        $instance = new class implements CustomTimeoutScheduler {
            public function runUnderTimeout(\Closure $f): ?TimeoutResult {
                return new DefaultTimeoutResult(true, null, null);
            }
        };

        $this->expectException(QuasiHttpException::class);
        $this->expectExceptionMessage("receive timeout");
        $this->expectExceptionCode(QuasiHttpException::REASON_CODE_TIMEOUT);
        
        ProtocolUtilsInternal::runTimeoutScheduler(
            $instance, false, $proc);
    }

    public function testRunTimeoutScheduler7() {
        $expected = null;
        $proc = fn() => $expected;
        $instance = new class implements CustomTimeoutScheduler {
            public function runUnderTimeout(\Closure $f): ?TimeoutResult {
                return new DefaultTimeoutResult(true, null,
                    new \InvalidArgumentException("risk"));
            }
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("risk");
        
        ProtocolUtilsInternal::runTimeoutScheduler(
            $instance, false, $proc);
    }

    /**
     * @dataProvider createTestContainsOnlyPrintableAsciiCharsData
    */
    public function testContainsOnlyPrintableAsciiChars(string $v,
            bool $allowSpace, bool $expected) {
        $actual = ProtocolUtilsInternal::containsOnlyPrintableAsciiChars(
            $v, $allowSpace);
        $this->assertSame($expected, $actual);
    }

    public static function createTestContainsOnlyPrintableAsciiCharsData() {
        return [
            ["x.n", false, true],
            ["x\n", false, false],
            ["yd\u{00c7}ea", true, false],
            ["x m", true, true],
            ["x m", false, false],
            ["x-yio", true, true],
            ["x-yio", false, true],
            ["x", true, true],
            ["x", false, true],
            [" !@#$%^&*()_+=-{}[]|\\:;\"'?/>.<,'",
                false, false],
            ["!@#$%^&*()_+=-{}[]|\\:;\"'?/>.<,'",
                false, true],
            [" !@#$%^&*()_+=-{}[]|\\:;\"'?/>.<,'",
                true, true]
        ];
    }

    /**
     * @dataProvider createContainsOnlyHeaderNameCharsData
    */
    public function testContainsOnlyHeaderNameChars(string $v, bool $expected) {
        $actual = ProtocolUtilsInternal::containsOnlyHeaderNameChars($v);
        $this->assertSame($expected, $actual);
    }

    public static function createContainsOnlyHeaderNameCharsData() {
        return [
            ["x\n", false],
            ["yd\u00c7ea", false],
            ["x m", false],
            ["xmX123abcD", true],
            ["xm", true],
            ["x-yio", true],
            ["x:yio", false],
            ["123", true],
            ["x", true]
        ];
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testValidateHttpHeaderSection1() {
        $csv = array(
            array("GET", "/", "HTTP/1.0", "24")
        );
        ProtocolUtilsInternal::validateHttpHeaderSection(false,
            $csv);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testValidateHttpHeaderSection2() {
        $csv = array(
            ["HTTP/1.0", "204", "No Content", "-10"],
            ["Content-Type", "application/json; charset=UTF8"],
            ["Transfer-Encoding", "chunked"],
            ["Date", "Tue, 15 Nov 1994 08:12:31 GMT"],
            ["Authorization", "Basic QWxhZGRpbjpvcGVuIHNlc2FtZQ=="],
            ["User-Agent", "Mozilla/5.0 (X11; Linux x86_64; rv:12.0) Gecko/20100101 Firefox/12.0"]
        );
        ProtocolUtilsInternal::validateHttpHeaderSection(true,
            $csv);
    }

    /**
     * @dataProvider createTestValidateHttpHeaderSectionForErrorsData
     */
    public function testValidateHttpHeaderSectionForErrors(bool $isResponse,
            array $csv,
            string $expectedErrorMessage) {
        $this->expectException(QuasiHttpException::class);
        $this->expectExceptionCode(QuasiHttpException::REASON_CODE_PROTOCOL_VIOLATION);
        $this->expectExceptionMessage($expectedErrorMessage);

        ProtocolUtilsInternal::validateHttpHeaderSection($isResponse, $csv);
    }

    public static function createTestValidateHttpHeaderSectionForErrorsData() {
        return [
            [
                false,
                [["HTTP/1 0", "20 4", "OK", "-10"]],
                "quasi http request line field contains spaces"
            ],
            [
                true,
                [["HTTP/1.0", "200", "OK", "-1 0"]],
                "quasi http status line field contains spaces"
            ],
            [
                true,
                [
                    ["HTTP/1.0", "200", "OK", "-51"],
                    ["Content:Type", "application/json; charset=UTF8"]
                ],
                "quasi http header name contains characters other than hyphen"
            ],
            [
                true,
                [
                    ["HTTP/1.0", "200", "OK", "-51"],
                    ["3", "application/json; charset=UTF8"]
                ],
                "quasi http header name cannot start with a number"
            ],
            [
                false,
                [
                    ["HTTP/1.0", "200", "OK", "51"],
                    ["Content-Type", "application/json; charset=UTF8\n"]
                ],
                "quasi http header value contains newlines"
            ]
        ];
    }

    /**
     * @dataProvider createTestEncodeQuasiHttpHeadersData
     */
    public function testEncodeQuasiHttpHeaders(bool $isResponse,
            array $reqOrStatusLine,
            ?array $remainingHeaders,
            string $expected) {
        $actual = ProtocolUtilsInternal::encodeQuasiHttpHeaders(
            $isResponse, $reqOrStatusLine, $remainingHeaders);
        $actual = MiscUtilsInternal::bytesToString($actual);
        $this->assertSame($expected, $actual);
    }

    public static function createTestEncodeQuasiHttpHeadersData() {
        return [
            [
                false,
                ["GET", "/home/index?q=results", "HTTP/1.1", "-1"],
                ["Content-Type"=> ["text/plain"]],
                "GET,/home/index?q=results,HTTP/1.1,-1\n" .
                    "Content-Type,text/plain\n"
            ],
            [
                true,
                ["HTTP/1.1", 200, "OK", 12],
                [
                    "Content-Type"=> ["text/plain", "text/csv"],
                    "Accept"=> ["text/html"],
                    "Accept-Charset"=> ["utf-8"]
                ],
                "HTTP/1.1,200,OK,12\n" .
                    "Content-Type,text/plain,text/csv\n" .
                    "Accept,text/html\n" .
                    "Accept-Charset,utf-8\n"
            ],
            [
                false,
                [null, null, null, 0],
                null,
                "\"\",\"\",\"\",0\n"
            ]
        ];
    }

    /**
     * @dataProvider createTestEncodeQuasiHttpHeadersForErrorsData
     */
    public function testEncodeQuasiHttpHeadersForErrors(bool $isResponse,
            array $reqOrStatusLine,
            ?array $remainingHeaders,
            string $expectedErrorMessage) {
        $this->expectException(QuasiHttpException::class);
        $this->expectExceptionCode(QuasiHttpException::REASON_CODE_PROTOCOL_VIOLATION);
        $this->expectExceptionMessage($expectedErrorMessage);
        
        ProtocolUtilsInternal::encodeQuasiHttpHeaders(
            $isResponse, $reqOrStatusLine, $remainingHeaders);
    }

    public static function createTestEncodeQuasiHttpHeadersForErrorsData() {
        return [
            [
                false,
                ["GET", "/home/index?q=results", "HTTP/1.1", "-1"],
                [""=> ["text/plain"]],
                "quasi http header name cannot be empty"
            ],
            [
                true,
                ["HTTP/1.1", 400, "Bad Request", 12],
                ["Content-Type"=> ["", "text/csv"]],
                "quasi http header value cannot be empty"
            ],
            [
                false,
                ["GET or POST", null, null, 0],
                null,
                "quasi http request line field contains spaces"
            ],
            [
                false,
                ["GET", null, null, "0 ior 1"],
                null,
                "quasi http request line field contains spaces"
            ],
            [
                true,
                [
                    "HTTP 1.1",
                    "200",
                    "OK",
                    "0"
                ],
                null,
                "quasi http status line field contains spaces"
            ]
        ];
    }

    /**
     * @dataProvider createTestDecodeQuasiHttpHeadersData
     */
    public function testDecodeQuasiHttpHeaders(bool $isResponse,
            string $buffer,
            array $expectedHeaders,
            array $expectedReqOrStatusLine) {
        $headersReceiver = [];
        $actualReqOrStatusLine = ProtocolUtilsInternal::decodeQuasiHttpHeaders(
            $isResponse, $buffer,
            $headersReceiver);
        $this->assertEquals($expectedReqOrStatusLine, $actualReqOrStatusLine);
        $this->assertEquals($expectedHeaders, $headersReceiver);
    }

    public static function createTestDecodeQuasiHttpHeadersData() {
        return [
            [
                false,
                "GET,/home/index?q=results,HTTP/1.1,-1\n" .
                    "Content-Type,text/plain\n",
                ["content-type"=> ["text/plain"]],
                [
                    "GET",
                    "/home/index?q=results",
                    "HTTP/1.1",
                    "-1"
                ]
            ],
            [
                true,
                "HTTP/1.1,200,OK,12\n" .
                    "Content-Type,text/plain,text/csv\n" .
                    "content-type,application/json\n" .
                    "\r\n" .
                    "ignored\n" .
                    "Accept,text/html\n" .
                    "Accept-Charset,utf-8\n",
                [
                    "content-type"=> [
                        "text/plain", "text/csv", "application/json"],
                    "accept"=> ["text/html"],
                    "accept-charset"=> ["utf-8"]
                ],
                [
                    "HTTP/1.1",
                    "200",
                    "OK",
                    "12"
                ]
            ],
            [
                false,
                "\"\",\"\",\"\",0\n",
                [],
                [
                    "",
                    "",
                    "",
                    "0"
                ]
            ]
        ];
    }

    /**
     * @dataProvider createTestDecodeQuasiHttpHeadersForErrorsData
     */
    public function testDecodeQuasiHttpHeadersForErrors(bool $isResponse,
            string $buffer, string $expectedErrorMessage) {
        $this->expectException(QuasiHttpException::class);
        $this->expectExceptionCode(QuasiHttpException::REASON_CODE_PROTOCOL_VIOLATION);
        $this->expectExceptionMessage($expectedErrorMessage);

        $headersReceiver = [];
        ProtocolUtilsInternal::decodeQuasiHttpHeaders(
            $isResponse, $buffer, $headersReceiver);
    }

    public static function createTestDecodeQuasiHttpHeadersForErrorsData() {
        return [
            [
                false,
                "\"k\n,lopp",
                "invalid quasi http headers"
            ],
            [
                false,
                "",
                "invalid quasi http headers"
            ],
            [
                true,
                "HTTP/1.1,200",
                "invalid quasi http status line"
            ],
            [
                false,
                "GET,HTTP/1.1,",
                "invalid quasi http request line"
            ]
        ];
    }
}