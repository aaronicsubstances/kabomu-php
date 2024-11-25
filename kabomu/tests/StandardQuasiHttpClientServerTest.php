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
            ?string $expectedSerializedReq) {
        $remoteEndpoint = new \stdclass;
        if ($expectedReqBodyBytes !== null) {
            $request->setBody(createReadableBuffer($expectedReqBodyBytes));
        }
        $dummyRes = new DefaultQuasiHttpResponse();
        $memOutputStream = createWritableBuffer();
        $sendOptions = new DefaultQuasiHttpProcessingOptions();
        $clientConnection = new QuasiHttpConnectionImpl();
        $clientConnection->setProcessingOptions($sendOptions);
        $clientConnection->setWritableStream($memOutputStream);
        $client = new StandardQuasiHttpClient();
        $transport = new class($remoteEndpoint, $sendOptions, $clientConnection) extends ClientTransportImpl {
            private readonly mixed $remoteEndpoint;
            private readonly ?QuasiHttpProcessingOptions $sendOptions;
            private readonly ?QuasiHttpConnection $clientConnection;

            public function __construct($remoteEndpoint, $sendOptions, $clientConnection) {
                parent::__construct(false);
                $this->remoteEndpoint = $remoteEndpoint;
                $this->sendOptions = $sendOptions;
                $this->clientConnection = $clientConnection;
            }

            public function allocateConnection($endPt, ?QuasiHttpProcessingOptions $opts): ?QuasiHttpConnection {
                Assert::assertSame($this->remoteEndpoint, $endPt);
                Assert::assertSame($this->sendOptions, $opts);
                return $this->clientConnection;
            }

            public function establishConnection(QuasiHttpConnection $conn) {
                Assert::assertSame($this->clientConnection, $conn);
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
                getWritableBufferContentsNow($memOutputStream));
        }

        // deserialize
        $memStream = createRandomizedReadableBuffer(
            getWritableBufferContentsNow($memOutputStream)
        );
        $actualRequest = null;
        $serverConnection = new QuasiHttpConnectionImpl();
        $serverConnection->setReadableStream($memStream);
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

        $expectedReqBodyBytes = null;
        $request = new DefaultQuasiHttpRequest();
        $expectedRequest = new DefaultQuasiHttpRequest();
        $expectedRequest->setHttpMethod("");
        $expectedRequest->setTarget("");
        $expectedRequest->setHttpVersion("");
        $expectedRequest->setContentLength(0);
        $expectedRequest->setHeaders(array());
        $expectedSerializedReq = "\x68\x64\x72\x73" .
            "\x00\x00\x00\x0b" .
            '"","","",0' . "\n";
        $testData[] = array($expectedReqBodyBytes, $request,
            $expectedRequest, $expectedSerializedReq);

        $expectedReqBodyBytes = "\x08\x07\x08\x09";
        $request = new DefaultQuasiHttpRequest();
        $request->setContentLength(-1);
        $expectedRequest = new DefaultQuasiHttpRequest();
        $expectedRequest->setHttpMethod("");
        $expectedRequest->setTarget("");
        $expectedRequest->setHttpVersion("");
        $expectedRequest->setContentLength(-1);
        $expectedRequest->setHeaders(array());
        $expectedSerializedReq = null;
        $testData[] = array($expectedReqBodyBytes, $request,
            $expectedRequest, $expectedSerializedReq);

        return $testData;
    }

    /**
     * @dataProvider createTestRequestSerializationForErrorsData
     */
    public function testRequestSerializationForErrors(
            QuasiHttpRequest $request,
            ?QuasiHttpProcessingOptions $sendOptions,
            ?string $expectedErrorMsg,
            ?string $expectedSerializedReq) {
        $remoteEndpoint = new \stdclass();
        $dummyRes = new DefaultQuasiHttpResponse();
        $memOutputStream = createWritableBuffer();
        $clientConnection = new QuasiHttpConnectionImpl();
        $clientConnection->setProcessingOptions($sendOptions);
        $clientConnection->setWritableStream($memOutputStream);
        $client = new StandardQuasiHttpClient();
        $transport = new class($remoteEndpoint, $sendOptions, $clientConnection) extends ClientTransportImpl {
            private readonly mixed $remoteEndpoint;
            private readonly ?QuasiHttpProcessingOptions $sendOptions;
            private readonly ?QuasiHttpConnection $clientConnection;

            public function __construct($remoteEndpoint, $sendOptions, $clientConnection) {
                parent::__construct(true);
                $this->remoteEndpoint = $remoteEndpoint;
                $this->sendOptions = $sendOptions;
                $this->clientConnection = $clientConnection;
            }

            public function allocateConnection($endPt, ?QuasiHttpProcessingOptions $opts): ?QuasiHttpConnection {
                Assert::assertSame($this->remoteEndpoint, $endPt);
                Assert::assertSame($this->sendOptions, $opts);
                return $this->clientConnection;
            }

            public function establishConnection(QuasiHttpConnection $conn) {
                Assert::assertSame($this->clientConnection, $conn);
            }
        };
        $client->setTransport($transport);
        $transport->responseDeserializer = function($conn) use($clientConnection, $dummyRes) {
            Assert::assertSame($clientConnection, $conn);
            return $dummyRes;
        };

        if ($expectedErrorMsg === null) {
            $actualRes = $client->send($remoteEndpoint, $request,
                $sendOptions);
            Assert::assertSame($dummyRes, $actualRes);

            if ($expectedSerializedReq !== null) {
                Assert::assertSame($expectedSerializedReq,
                    getWritableBufferContentsNow($memOutputStream));
            }
        }
        else {
            $this->expectException(\Throwable::class);
            $this->expectExceptionMessage($expectedErrorMsg);

            $client->send($remoteEndpoint, $request, $sendOptions);
        }
    }

    public static function createTestRequestSerializationForErrorsData() {
        $testData = [];

        $request = new DefaultQuasiHttpRequest();
        $request->setHttpMethod("POST");
        $request->setTarget("/Update");
        $request->setContentLength(8);
        $sendOptions = new DefaultQuasiHttpProcessingOptions();
        $sendOptions->setMaxHeadersSize(18);
        $expectedErrorMsg = null;
        $expectedSerializedReq = "\x68\x64\x72\x73" .
            "\x00\x00\x00\x12" . 
            "POST,/Update,\"\",8\n";
        $testData[] = array($request, $sendOptions, $expectedErrorMsg,
            $expectedSerializedReq);

        $requestBodyBytes = "\x04";
        $request = new DefaultQuasiHttpRequest();
        $request->setHttpMethod("PUT");
        $request->setTarget("/Updates");
        $request->setContentLength(0);
        $request->setBody(createReadableBuffer($requestBodyBytes));
        $sendOptions = new DefaultQuasiHttpProcessingOptions();
        $sendOptions->setMaxHeadersSize(19);
        $expectedErrorMsg = null;
        $expectedSerializedReq = "\x68\x64\x72\x73" .
            "\x00\x00\x00\x12" . 
            "PUT,/Updates,\"\",0\n" .
            "\x62\x64\x74\x61" .
            "\x00\x00\x00\x01\x04".
            "\x62\x64\x74\x61" .
            "\x00\x00\x00\x00";
        $testData[] = array($request, $sendOptions, $expectedErrorMsg,
            $expectedSerializedReq);

        $requestBodyBytes = "\x04\x05\x06";
        $request = new DefaultQuasiHttpRequest();
        $request->setContentLength(10);
        $request->setBody(createReadableBuffer($requestBodyBytes));
        $sendOptions = null;
        $expectedErrorMsg = null;
        $expectedSerializedReq = "\x68\x64\x72\x73" .
            "\x00\x00\x00\x0c" . 
            "\"\",\"\",\"\",10\n" .
            $requestBodyBytes;
        $testData[] = array($request, $sendOptions, $expectedErrorMsg,
            $expectedSerializedReq);

        $request = new DefaultQuasiHttpRequest();
        $sendOptions = new DefaultQuasiHttpProcessingOptions();
        $sendOptions->setMaxHeadersSize(5);
        $expectedErrorMsg = "quasi http headers exceed max size";
        $expectedSerializedReq = null;
        $testData[] = array($request, $sendOptions, $expectedErrorMsg,
            $expectedSerializedReq);

        $request = new DefaultQuasiHttpRequest();
        $request->setHttpVersion("no-spaces-allowed");
        $request->setHeaders([
            "empty-prohibited"=> ["a: \nb"]
        ]);
        $sendOptions = null;
        $expectedErrorMsg = "quasi http header value contains newlines";
        $expectedSerializedReq = null;
        $testData[] = array($request, $sendOptions, $expectedErrorMsg,
            $expectedSerializedReq);

        return $testData;
    }
}
