<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\ByteStream\ClosedException;
use Amp\ByteStream\StreamException;
use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\ReadableStreamIteratorAggregate;
use Amp\ByteStream\WritableStream;

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
final class WritableBufferInternal implements WritableStream
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

class PushbackReadableInternal implements ReadableStream, \IteratorAggregate {
    use ReadableStreamIteratorAggregate;
    use ForbidCloning;
    use ForbidSerialization;

    private readonly mixed $backingStream;
    private array $buf;

    private bool $reading = false;

    private bool $closed = FALSE;

    public function __construct($backingStream) {
        if (!$backingStream) {
            throw new \InvalidArgumentException("Expected a backing stream");
        }
        $this->backingStream = $backingStream;
        $this->buf = [];
    }

    public function read(?Cancellation $cancellation = null): ?string {
        if ($this->reading) {
            throw new PendingReadError;
        }
        $this->reading = true;
        try {
            if ($this->closed) {
                throw new ClosedException;
            }
            if ($this->buf) {
                $chunk = array_pop($this->buf);
                return $chunk;
            }
            return $this->backingStream->read($cancellation);
        }
        finally {
            $this->reading = false;
        }
    }

    /**
     * Pushes back an array of bytes by pushing it to the front of the
     * pushback buffer. After this method returns, the next chunk to be read
     * will be $data.
     *
     * @param string $data the byte array to push back
     */
    public function unread(?string &$data): void {
        if ($data === null) {
            return;
        }
        if ($this->reading) {
            throw new PendingReadError;
        }
        $this->reading = true;
        try {
            if ($this->closed) {
                throw new ClosedException;
            }
            $this->buf[] = &$data;
        }
        finally {
            $this->reading = false;
        }
    }

    public function isReadable(): bool {
        if (!$this->closed && $this->buf) {
            return TRUE;
        }
        return $this->backingStream->isReadable();
    }

    /**
     * Closes the resource, marking it as unusable.
     * Whether pending operations are aborted or not is implementation dependent.
     */
    public function close(): void {
        $this->closed = TRUE;
        $this->backingStream->close();
    }

    /**
     * Returns whether this resource has been closed.
     *
     * @return bool `true` if closed, otherwise `false`.
     */
    public function isClosed(): bool {
        return $this->backingStream->isClosed();
    }

    /**
     * Registers a callback that is invoked when this resource is closed.
     *
     * @param \Closure():void $onClose
     */
    public function onClose(\Closure $onClose): void {
        $this->backingStream->onClose($onClose);
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