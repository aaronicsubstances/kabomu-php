<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

use PHPUnit\Framework\TestCase;

use AaronicSubstances\Kabomu\Abstractions\DefaultQuasiHttpProcessingOptions;

class QuasiHttpUtilsTest extends TestCase {

    public function testClassConstants() {
        $this->assertSame("CONNECT", QuasiHttpUtils::METHOD_CONNECT);
        $this->assertSame("DELETE", QuasiHttpUtils::METHOD_DELETE);
        $this->assertSame("GET", QuasiHttpUtils::METHOD_GET);
        $this->assertSame("HEAD", QuasiHttpUtils::METHOD_HEAD);
        $this->assertSame("OPTIONS", QuasiHttpUtils::METHOD_OPTIONS);
        $this->assertSame("PATCH", QuasiHttpUtils::METHOD_PATCH);
        $this->assertSame("POST", QuasiHttpUtils::METHOD_POST);
        $this->assertSame("PUT", QuasiHttpUtils::METHOD_PUT);
        $this->assertSame("TRACE", QuasiHttpUtils::METHOD_TRACE);

        $this->assertSame(200, QuasiHttpUtils::STATUS_CODE_OK);
        $this->assertSame(500, QuasiHttpUtils::STATUS_CODE_SERVER_ERROR);
        $this->assertSame(400, QuasiHttpUtils::STATUS_CODE_CLIENT_ERROR_BAD_REQUEST);
        $this->assertSame(401, QuasiHttpUtils::STATUS_CODE_CLIENT_ERROR_UNAUTHORIZED);
        $this->assertSame(403, QuasiHttpUtils::STATUS_CODE_CLIENT_ERROR_FORBIDDEN);
        $this->assertSame(404, QuasiHttpUtils::STATUS_CODE_CLIENT_ERROR_NOT_FOUND);
        $this->assertSame(405, QuasiHttpUtils::STATUS_CODE_CLIENT_ERROR_METHOD_NOT_ALLOWED);
        $this->assertSame(413, QuasiHttpUtils::STATUS_CODE_CLIENT_ERROR_PAYLOAD_TOO_LARGE);
        $this->assertSame(414, QuasiHttpUtils::STATUS_CODE_CLIENT_ERROR_URI_TOO_LONG);
        $this->assertSame(415, QuasiHttpUtils::STATUS_CODE_CLIENT_ERROR_UNSUPPORTED_MEDIA_TYPE);
        $this->assertSame(422, QuasiHttpUtils::STATUS_CODE_CLIENT_ERROR_UNPROCESSABLE_ENTITY);
        $this->assertSame(429, QuasiHttpUtils::STATUS_CODE_CLIENT_ERROR_TOO_MANY_REQUESTS);
    }

    public function testMergeProcessingOptions1() {
        $preferred = null;
        $fallback = null;
        $actual = QuasiHttpUtils::mergeProcessingOptions($preferred, $fallback);
        $this->assertNull($actual);
    }

    public function testMergeProcessingOptions2() {
        $preferred = new DefaultQuasiHttpProcessingOptions();
        $fallback = null;
        $actual = QuasiHttpUtils::mergeProcessingOptions($preferred, $fallback);
        $this->assertSame($preferred, $actual);
    }

    public function testMergeProcessingOptions3() {
        $preferred = null;
        $fallback = new DefaultQuasiHttpProcessingOptions();
        $actual = QuasiHttpUtils::mergeProcessingOptions($preferred, $fallback);
        $this->assertSame($fallback, $actual);
    }

    public function testMergeProcessingOptions4() {
        $preferred = new DefaultQuasiHttpProcessingOptions();
        $fallback = new DefaultQuasiHttpProcessingOptions();
        $actual = QuasiHttpUtils::mergeProcessingOptions($preferred, $fallback);
        $expected = new DefaultQuasiHttpProcessingOptions();
        $expected->setExtraConnectivityParams([]);
        \AaronicSubstances\Kabomu\compareProcessingOptions($expected, $actual);
    }

    public function testMergeProcessingOptions5() {
        $preferred = new DefaultQuasiHttpProcessingOptions();
        $preferred->setExtraConnectivityParams(array("scheme"=> "tht"));
        $preferred->setMaxHeadersSize(10);
        $preferred->setMaxResponseBodySize(-1);
        $preferred->setTimeoutMillis(0);

        $fallback = new DefaultQuasiHttpProcessingOptions();
        $fallback->setExtraConnectivityParams(["scheme"=> "htt", "two"=> 2]);
        $fallback->setMaxHeadersSize(30);
        $fallback->setMaxResponseBodySize(40);
        $fallback->setTimeoutMillis(-1);

        $expected = new DefaultQuasiHttpProcessingOptions();
        $expected->setExtraConnectivityParams(["scheme"=> "tht", "two"=> 2]);
        $expected->setMaxHeadersSize(10);
        $expected->setMaxResponseBodySize(-1);
        $expected->setTimeoutMillis(-1);

        $actual = QuasiHttpUtils::mergeProcessingOptions(
            $preferred, $fallback);
        \AaronicSubstances\Kabomu\compareProcessingOptions($expected, $actual);
    }

    /**
     * @dataProvider createTestDetermineEffectiveNonZeroIntegerOptionData
    */
    public function testDetermineEffectiveNonZeroIntegerOption(
            ?int $preferred, ?int $fallback1, int $defaultValue, int $expected) {
        $actual = QuasiHttpUtils::determineEffectiveNonZeroIntegerOption(
            $preferred, $fallback1, $defaultValue);
        $this->assertSame($expected, $actual);
    }

    public static function createTestDetermineEffectiveNonZeroIntegerOptionData() {
        return [
            [1, null, 20, 1],
            [5, 3, 11, 5],
            [-15, 3, -1, -15],
            [null, 3, -1, 3],
            [null, -3, -1, -3],
            [null, null, 2, 2],
            [null, null, -8, -8],
            [null, null, 0, 0]
        ];
    }

    /**
     * @dataProvider createTestDetermineEffectivePositiveIntegerOptionData
    */
    public function testDetermineEffectivePositiveIntegerOption(
            ?int $preferred, ?int $fallback1, int $defaultValue, int $expected) {
        $actual = QuasiHttpUtils::determineEffectivePositiveIntegerOption(
            $preferred, $fallback1, $defaultValue);
        $this->assertSame($expected, $actual);
    }

    public static function createTestDetermineEffectivePositiveIntegerOptionData() {
        return [
            [null, 1, 30, 1],
            [5, 3, 11, 5],
            [null, 3, -1, 3],
            [null, null, 2, 2],
            [null, null, -8, -8],
            [null, null, 0, 0]
        ];
    }

    /**
     * @dataProvider createTestDetermineEffectiveOptionsData
    */
    public function testDetermineEffectiveOptions($preferred, $fallback, $expected) {
        $actual = QuasiHttpUtils::determineEffectiveOptions($preferred, $fallback);
        $this->assertEquals($expected, $actual);
    }

    public static function createTestDetermineEffectiveOptionsData() {
        return [
            [
                null,
                null,
                []
            ],
            [
                [],
                [],
                []
            ],
            [
                ["a"=> 2, "b"=> 3],
                null,
                ["a"=> 2, "b"=> 3],
            ],
            [
                null,
                ["a"=> 2, "b"=> 3],
                ["a"=> 2, "b"=> 3],
            ],
            [
                ["a"=> 2, "b"=> 3],
                ["c"=> 4, "d"=> 3],
                ["a"=> 2, "b"=> 3, "c"=> 4, "d"=> 3],
            ],
            [
                ["a"=> 2, "b"=> 3],
                ["a"=> 4, "d"=> 3],
                ["a"=> 2, "b"=> 3, "d"=> 3],
            ],
            [
                ["a"=> 2],
                ["a"=> 4, "d"=> 3],
                ["a"=> 2, "d"=> 3],
            ]
        ];
    }
}