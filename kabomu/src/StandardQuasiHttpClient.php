<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

use AaronicSubstances\Kabomu\Abstractions\CustomTimeoutScheduler;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpAltTransport;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpClientTransport;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpConnection;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpProcessingOptions;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpRequest;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpResponse;
use AaronicSubstances\Kabomu\Abstractions\DeserializerFunction;
use AaronicSubstances\Kabomu\Abstractions\SerializerFunction;
use AaronicSubstances\Kabomu\Exceptions\MissingDependencyException;
use AaronicSubstances\Kabomu\Exceptions\QuasiHttpException;

/**
 * The standard implementation of the client side of the quasi http protocol
 * defined by the Kabomu library.
 *
 * This class provides the client facing side of networking for end users.
 * It is the complement to the  {@link StandardQuasiHttpServer} class for
 * supporting the semantics of HTTP client libraries
 * whiles enabling underlying transport options beyond TCP.
 *
 * Therefore this class can be seen as the equivalent of an HTTP client
 * that extends underlying transport beyond TCP
 * to IPC mechanisms.
 */
class StandardQuasiHttpClient {

    private ?QuasiHttpClientTransport $transport = null;

    /**
     * Creates a new instance.
     */
    public function __construct() {
    }

    /**
     * Gets the underlying transport (TCP or IPC) by which connections
     * will be allocated for sending requests and receiving responses.
     * @return quasi http transport
     */
    public function getTransport(): ?QuasiHttpClientTransport {
        return $this->transport;
    }

    /**
     * Sets the underlying transport (TCP or IPC) by which connections
     * will be allocated for sending requests and receiving responses.
     * @param ?QuasiHttpClientTransport transport quasi http transport
     */
    public function setTransport(?QuasiHttpClientTransport $transport) {
        $this->transport = $transport;
    }

    /**
     * Sends a quasi http request via quasi http transport and with
     * send options specified.
     * @param mixed $remoteEndpoint the destination endpoint of the request
     * @param QuasiHttpRequest $request the request to send
     * @param ?QuasiHttpProcessingOptions $options optional send options
     * @return QuasiHttpResponse the quasi http response returned from the remote endpoint
     * @throws MissingDependencyException if the transport property is null
     * @throws QuasiHttpException if an error occurs with request processing.
     */
    public function send($remoteEndpoint,
            QuasiHttpRequest $request, ?QuasiHttpProcessingOptions $options): QuasiHttpResponse  {
        return $this->sendInternal($remoteEndpoint, $request, null, $options);
    }

    /**
     * Sends a quasi http request via quasi http transport and makes it
     * posssible to receive connection allocation information before
     * creating request.
     * @param mixed $remoteEndpoint the destination endpoint of the request
     * @param \Closure $requestFunc a callback which receives the environment of an established connection, and must
     * return an instance of {@link QuasiHttpRequest}.
     * @param ?QuasiHttpProcessingOptions $options optional send options
     * @return QuasiHttpResponse the quasi http response returned from the remote endpoint.
     * @throws MissingDependencyException if the transport property is null
     * @throws QuasiHttpException if an error occurs with request processing.
     */
    public function send2($remoteEndpoint,
            \Closure $requestFunc,
            ?QuasiHttpProcessingOptions $options): QuasiHttpResponse {
        return $this->sendInternal($remoteEndpoint, null, $requestFunc, $options);
    }

    private function sendInternal($remoteEndpoint, ?QuasiHttpRequest $request,
            ?\Closure $requestFunc,
            ?QuasiHttpProcessingOptions $sendOptions): QuasiHttpResponse {
        // access fields for use per request call, in order to cooperate with
        // any implementation of field accessors which supports
        // concurrent modifications.
        $transport = $this->transport;

        if (!$transport) {
            throw new MissingDependencyException("client transport");
        }

        $connection = null;
        try {
            $connection = $transport->allocateConnection($remoteEndpoint, $sendOptions);
            if (!$connection) {
                throw new QuasiHttpException("no connection");
            }
            $timeoutScheduler = $connection->getTimeoutScheduler();
            if ($timeoutScheduler) {
                $proc = fn() => self::processSend(
                    $request, $requestFunc, $transport, $connection);
                $response = ProtocolUtilsInternal::runTimeoutScheduler(
                    $timeoutScheduler, true, $proc);
            }
            else {
                $response = self::processSend($request, $requestFunc,
                    $transport, $connection);
            }
            
            $transport->releaseConnection($connection, $response);
            return $response;
        }
        catch (\Throwable $e) {
            if ($connection) {
                try {
                    $transport->releaseConnection($connection, null);
                }
                catch (\Throwable $ignore) { }
            }
            if ($e instanceof QuasiHttpException) {
                throw $e;
            }
            //throw $e;
            $abortError = new QuasiHttpException(
                "encountered error during send request processing",
                QuasiHttpException::REASON_CODE_GENERAL,
                $e);
            throw $abortError;
        }
    }

    private static function processSend(?QuasiHttpRequest $request,
            ?\Closure $requestFunc,
            QuasiHttpClientTransport $transport,
            QuasiHttpConnection $connection): QuasiHttpResponse {
        // wait for connection to be completely established.
        $transport->establishConnection($connection);

        if (!$request) {
            $request = $requestFunc($connection->getEnvironment());
            if (!$request) {
                throw new QuasiHttpException("no request");
            }
            if (!($request instanceof QuasiHttpRequest)) {
                throw new QuasiHttpException(
                    "didn't get instance of QuasiHttpRequest class from custom request generator");
            }
        }

        // send entire request first before
        // receiving of response.
        $altTransport = null;
        $requestSerializer = null;
        if ($transport instanceof QuasiHttpAltTransport) {
            $altTransport = $transport;
            $requestSerializer = $altTransport->getRequestSerializer();
        }
        $requestSerialized = false;
        if ($requestSerializer) {
            $requestSerialized = $requestSerializer($connection, $request);
        }
        if (!$requestSerialized) {
            ProtocolUtilsInternal::writeEntityToTransport(
                false, $request, $transport->getWritableStream($connection),
                $connection);
        }

        $response = null;
        $responseDeserializer = $altTransport?->getResponseDeserializer();
        if ($responseDeserializer) {
            $response = $responseDeserializer($connection);
            if ($response && !($response instanceof QuasiHttpResponse)) {
                throw new QuasiHttpException(
                    "didn't get instance of QuasiHttpResponse class from custom response deserializer");
            }
        }
        if (!$response) {
            $response = ProtocolUtilsInternal::readEntityFromTransport(
                true, $transport->getReadableStream($connection), $connection);
            $response->setDisposer(fn() => $transport->releaseConnection($connection, null));
        }
        return $response;
    }
}
