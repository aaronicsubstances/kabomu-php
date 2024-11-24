<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

use Amp\PHPUnit\AsyncTestCase;
use PHPUnit\Framework\Assert;

use AaronicSubstances\Kabomu\Abstractions\DefaultQuasiHttpProcessingOptions;
use AaronicSubstances\Kabomu\Abstractions\DefaultQuasiHttpRequest;
use AaronicSubstances\Kabomu\Abstractions\DefaultQuasiHttpResponse;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpProcessingOptions;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpConnection;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpRequest;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpResponse;
use AaronicSubstances\Kabomu\Exceptions\KabomuIOException;

class StandardQuasiHttpClientServerTest extends AsyncTestCase  {
    
    /**
     * @dataProvider createTestRequestSerializationData
     */
    public function testRequestSerialization(
            ?string $expectedReqBodyBytes,
            QuasiHttpRequest $request,
            QuasiHttpRequest $expectedRequest,
            string $expectedSerializedReq) {
        $remoteEndpoint = new \stdclass;
        if ($expectedReqBodyBytes !== null) {
            $request->setBody(createUnreadEnabledReadableBuffer($expectedReqBodyBytes));
        }
        $dummyRes = new DefaultQuasiHttpResponse();
        $memOutputStream = new WritableBuffer2();
        $sendOptions = new DefaultQuasiHttpProcessingOptions();
        $clientConnection = new QuasiHttpConnectionImpl();
        $clientConnection->setProcessingOptions($sendOptions);
        $clientConnection->setWritableStream($memOutputStream);
        $client = new StandardQuasiHttpClient();
        $transport = new class extends ClientTransportImpl {

            public function __construct() {
                parent::__construct(false);
            }

            public function allocateConnection($endPt, ?QuasiHttpProcessingOptions $opts): ?QuasiHttpConnection {
                Assert::assertSame($remoteEndpoint, $endPt);
                Assert::assertSame($sendOptions, $opts);
                return $clientConnection;
            }

            public function establishConnection(QuasiHttpConnection $conn) {
                Assert::assertSame($clientConnection, $conn);
            }
        };
        $client->setTransport($transport);
        $transport->responseDeserializer = function($conn) use($clientConnection, $dummyRes) {
            Assert::assertSame($clientConnection, $conn);
            return $dummyRes;
        };
        $actualRes = $client->send($remoteEndpoint, $request, $sendOptions);
        $this->assertSame($dummyRes, $actualRes);

        if ($expectedSerializedReq !== null) {
            $this->assertSame($expectedSerializedReq,
                $memOutputStream->getContentsNow());
        }

        // reset for reading.
        $memInputStream = createUnreadEnabledReadableBuffer(
            $memOutputStream->getContentsNow()
        );

        // deserialize
        $actualRequest = null;
        $serverConnection = new QuasiHttpConnectionImpl();
        $serverConnection->setReadableStream(
            createRandomizedReadInputStream($memInputStream->getContentsNow(), false));
        $serverConnection->setEnvironment([]);
        $server = new StandardQuasiHttpServer();
        $serverTransport = new ServerTransportImpl();
        $serverTransport->responseSerializer = function($conn, $res) use($serverConnection, $dummyRes) {
            Assert::assertSame($serverConnection, $conn);
            Assert::assertSame($dummyRes, $res);
            return true;
        };
        $server->setTransport($serverTransport);
        $server->setApplication(function($req) use(&$actualRequest, $dummyRes) {
            $actualRequest = $req;
            return $dummyRes;
        });
        $server->acceptConnection($serverConnection);

        // assert
        compareRequests($expectedRequest,
            $actualRequest, $expectedReqBodyBytes);
        $this->assertEquals($serverConnection->getEnvironment(),
            $actualRequest->getEnvironment());
    }

    public static function createTestRequestSerializationData() {
        $testData = array();

        $expectedReqBodyBytes = "tanner";
        $request = new DefaultQuasiHttpRequest();
        $request->setHttpMethod("GET");
        $request->setTarget("/");
        $request->setHttpVersion("HTTP/1.0");
        $request->setContentLength(strlen($expectedReqBodyBytes));
        $request->setHeaders(array(
            "Accept"=> ["text/plain", "text/csv"],
            "Content-Type"=> ["application/json,charset=UTF-8"]));
        $expectedRequest = new DefaultQuasiHttpRequest();
        $expectedRequest->setHttpMethod("GET");
        $expectedRequest->setTarget("/");
        $expectedRequest->setHttpVersion("HTTP/1.0");
        $expectedRequest->setContentLength(strlen($expectedReqBodyBytes));
        $expectedRequest->setHeaders(array(
            "accept"=> ["text/plain", "text/csv"],
            "content-type"=> ["application/json,charset=UTF-8"]));
        $expectedSerializedReq = "\x68\x64\x72\x73" .
            "\x00\x00\x00\x5a" .
            "GET,/,HTTP/1.0,6\n" .
            "Accept,text/plain,text/csv\n" .
            "Content-Type,\"application/json,charset=UTF-8\"\n" .
            $expectedReqBodyBytes;
        $testData[] = array($expectedReqBodyBytes, $request,
            $expectedRequest, $expectedSerializedReq);

        return $testData;
    }
}
