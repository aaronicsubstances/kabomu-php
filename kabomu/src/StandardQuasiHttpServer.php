<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

use AaronicSubstances\Kabomu\Abstractions\CustomTimeoutScheduler;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpAltTransport;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpApplication;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpConnection;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpRequest;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpResponse;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpServerTransport;
use AaronicSubstances\Kabomu\Abstractions\DeserializerFunction;
use AaronicSubstances\Kabomu\Abstractions\SerializerFunction;
use AaronicSubstances\Kabomu\Exceptions\MissingDependencyException;
use AaronicSubstances\Kabomu\Exceptions\QuasiHttpException;

/**
 * The standard implementation of the server side of the quasi http protocol
 * defined by the Kabomu library.
 *
 * This class provides the server facing side of networking for end users.
 * It is the complement to the {@link StandardQuasiHttpClient} class for
 * providing HTTP semantics whiles enabling underlying transport options
 * beyond TCP.
 *
 * Therefore this class can be seen as the equivalent of an HTTP server
 * in which the underlying transport of
 * choice extends beyond TCP to include IPC mechanisms.
 */
class StandardQuasiHttpServer {
    private ?\Closure $application = null;
    private ?QuasiHttpServerTransport $transport = null;

    /**
     * Creates a new instance.
     */
    public function __construct() {
    }

    /**
     * Gets the closure which is
     * responsible for processing requests to generate responses.
     * @return ?QuasiHttpApplication quasi http application
     * 
     * @see self::setApplication()
     */
    public function getApplication(): ?\Closure {
        return $this->application;
    }

    /**
     * Sets the closure which is
     * responsible for processing requests to generate responses. It takes
     * an instance of {@link QuasiHttpRequest} and must return an instance of
     * {@link QuasiHttpResponse} or null.
     * @param ?\Closure closure which serves as quasi http application.
     */
    public function setApplication(?\Closure $application) {
        $this->application = $application;
    }

    /**
     * Gets the underlying transport (TCP or IPC) for retrieving requests
     * for quasi web applications, and for sending responses generated from
     * quasi web applications.
     * @return ?QuasiHttpServerTransport quasi http transport
     */
    public function getTransport(): ?QuasiHttpServerTransport {
        return $this->transport;
    }

    /**
     * Sets the underlying transport (TCP or IPC) for retrieving requests
     * for quasi web applications, and for sending responses generated from
     * quasi web applications.
     * @param ?QuasiHttpServerTransport transport quasi http transport
     */
    public function setTransport(?QuasiHttpServerTransport $transport) {
        $this->transport = $transport;
    }

    /**
     * Used to process incoming connections from quasi http server transports.
     * @param QuasiHttpConnection connection represents a quasi http connection
     * @throws MissingDependencyException if the transport property
     * or the application property is null.
     * @throws QuasiHttpException if an error occurs with request processing.
     */
    public function acceptConnection(QuasiHttpConnection $connection) {
        // access fields for use per processing call, in order to cooperate with
        // any implementation of field accessors which supports
        // concurrent modifications.
        $transport = $this->transport;
        $application = $this->application;
        if (!$transport) {
            throw new MissingDependencyException("server transport");
        }
        if (!$application) {
            throw new MissingDependencyException("server application");
        }

        try {
            $timeoutScheduler = $connection->getTimeoutScheduler();
            if ($timeoutScheduler) {
                $proc = fn() => self::processAccept(
                    $application, $transport, $connection);
                ProtocolUtilsInternal::runTimeoutScheduler(
                    $timeoutScheduler, false, $proc);
            }
            else {
                self::processAccept($application, $transport,
                    $connection);
            }
            $transport->releaseConnection($connection);
        }
        catch (\Throwable $e) {
            try {
                $transport->releaseConnection($connection);
            }
            catch (\Throwable $ignore) { }
            if ($e instanceof QuasiHttpException) {
                throw $e;
            }
            $abortError = new QuasiHttpException(
                "encountered error during receive request processing: " . $e->getMessage(),
                QuasiHttpException::REASON_CODE_GENERAL,
                $e);
            throw $abortError;
        }
    }

    private static function processAccept(\Closure $application,
            QuasiHttpServerTransport $transport,
            QuasiHttpConnection $connection) {
        $altTransport = null;
        $requestDeserializer = null;
        if ($transport instanceof QuasiHttpAltTransport) {
            $altTransport = $transport;
            $requestDeserializer = $altTransport->getRequestDeserializer();
        }
        $request = null;
        if ($requestDeserializer) {
            $request = $requestDeserializer($connection);
            if ($request && !($request instanceof QuasiHttpRequest)) {
                throw new QuasiHttpException(
                    "didn't get instance of QuasiHttpRequest class from custom request deserializer");
            }
        }
        if (!$request) {
            $request = ProtocolUtilsInternal::readEntityFromTransport(
                false, $transport->getReadableStream($connection), $connection);
        }

        $response = $application($request);
        if (!$response) {
            throw new QuasiHttpException("no response");
        }
        if (!($response instanceof QuasiHttpResponse)) {
            throw new QuasiHttpException(
                "didn't get instance of QuasiHttpResponse class from quasi http application");
        }

        try {
            $responseSerialized = false;
            $responseSerializer = $altTransport?->getResponseSerializer();
            if ($responseSerializer) {
                $responseSerialized = $responseSerializer($connection, $response);
            }
            if (!$responseSerialized) {
                ProtocolUtilsInternal::writeEntityToTransport(
                    true, $response, $transport->getWritableStream($connection),
                    $connection);
            }
            return null;
        }
        finally {
            $response->release();
        }
    }
}
