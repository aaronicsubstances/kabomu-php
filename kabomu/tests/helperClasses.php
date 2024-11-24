<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

use Amp\ByteStream\WritableStream;
use Amp\DeferredFuture;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;

use AaronicSubstances\Kabomu\Abstractions\CustomTimeoutScheduler;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpConnection;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpProcessingOptions;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpResponse;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpAltTransport;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpClientTransport;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpServerTransport;
use AaronicSubstances\Kabomu\Abstractions\DeserializerFunction;
use AaronicSubstances\Kabomu\Abstractions\SerializerFunction;

/**
 * Copied and modified from https://github.com/amphp/byte-stream/blob/2.x/src/WritableBuffer.php
 */
final class WritableBuffer2 implements WritableStream
{
    use ForbidCloning;
    use ForbidSerialization;

    private readonly DeferredFuture $deferredFuture;

    private string $contents = '';

    private bool $closed = false;

    public function __construct()
    {
        $this->deferredFuture = new DeferredFuture;
    }

    public function write(string $bytes): void
    {
        if ($this->closed) {
            throw new ClosedException("The stream has already been closed");
        }

        $this->contents .= $bytes;
    }

    public function end(): void
    {
        if ($this->closed) {
            throw new ClosedException("The stream has already been closed");
        }

        $this->close();
    }

    public function isWritable(): bool
    {
        return !$this->closed;
    }

    public function getContentsNow(): string
    {
        return $this->contents;
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;

        $this->deferredFuture->complete($this->contents);
        $this->contents = '';
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function onClose(\Closure $onClose): void
    {
        $this->deferredFuture->getFuture()->finally($onClose);
    }
}

abstract class ClientTransportImpl implements QuasiHttpClientTransport, QuasiHttpAltTransport {
    public ?\Closure  $requestSerializer = null;
    public ?\Closure  $responseSerializer = null;
    public ?\Closure  $requestDeserializer = null;
    public ?\Closure  $responseDeserializer = null;

    public function __construct(bool $initializeSerializerFunctions) {
        if (!$initializeSerializerFunctions) {
            return;
        }
        $requestSerializer = fn($x, $y) => false;
        $responseSerializer = fn($x, $y) => false;
        $requestDeserializer = fn($x) => null;
        $responseDeserializer = fn($x) => null;
    }

    public function getReadableStream(QuasiHttpConnection $connection) {
        return $connection->getReadableStream();
    }

    public function getWritableStream(QuasiHttpConnection $connection) {
        return $connection->getWritableStream();
    }

    public function getRequestSerializer(): ?\Closure  {
        return $this->requestSerializer;
    }

    public function getResponseSerializer(): ?\Closure  {
        return $this->responseSerializer;
    }

    public function getRequestDeserializer(): ?\Closure  {
        return $this->requestDeserializer;
    }

    public function getResponseDeserializer(): ?\Closure  {
        return $this->responseDeserializer;
    }

    public function releaseConnection(QuasiHttpConnection $connection, ?QuasiHttpResponse $response) {
    }
}

class ServerTransportImpl implements QuasiHttpServerTransport, QuasiHttpAltTransport {
    public ?\Closure $requestSerializer = null;
    public ?\Closure  $responseSerializer = null;
    public ?\Closure  $requestDeserializer = null;
    public ?\Closure  $responseDeserializer = null;

    public function getReadableStream(QuasiHttpConnection $connection) {
        return $connection->getReadableStream();
    }

    public function getWritableStream(QuasiHttpConnection $connection) {
        return $connection->getWritableStream();
    }

    public function getRequestSerializer(): ?\Closure  {
        return $this->requestSerializer;
    }

    public function getResponseSerializer(): ?\Closure  {
        return $this->responseSerializer;
    }

    public function getRequestDeserializer(): ?\Closure  {
        return $this->requestDeserializer;
    }

    public function getResponseDeserializer(): ?\Closure  {
        return $this->responseDeserializer;
    }

    public function releaseConnection(QuasiHttpConnection $connection) {
    }
}

class QuasiHttpConnectionImpl implements QuasiHttpConnection {
    private $readableStream = null;
    private $writableStream = null;
    private ?QuasiHttpProcessingOptions $processingOptions = null;
    private ?array $environment = null;
    
    public function getReadableStream() {
        return $this->readableStream;
    }

    public function setReadableStream($readableStream) {
        $this->readableStream = $readableStream;
    }

    public function getWritableStream() {
        return $this->writableStream;
    }

    public function setWritableStream($writableStream) {
        $this->writableStream = $writableStream;
    }

    public function getProcessingOptions(): ?QuasiHttpProcessingOptions {
        return $this->processingOptions;
    }

    public function setProcessingOptions(?QuasiHttpProcessingOptions $processingOptions) {
        $this->processingOptions = $processingOptions;
    }

    public function getEnvironment(): ?array {
        return $this->environment;
    }

    public function setEnvironment(?array $environment) {
        $this->environment = $environment;
    }

    public function getTimeoutScheduler(): ?\Closure {
        return null;
    }
}