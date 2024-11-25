<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Tlv;

use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\ByteStream\ClosedException;
use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\ReadableStreamIteratorAggregate;
use Amp\ByteStream\PendingReadError;

use AaronicSubstances\Kabomu\Exceptions\ExpectationViolationException;
use AaronicSubstances\Kabomu\Exceptions\KabomuIOException;

class ContentLengthEnforcingStreamInternal implements ReadableStream, \IteratorAggregate {
    use ReadableStreamIteratorAggregate;
    use ForbidCloning;
    use ForbidSerialization;

    private readonly mixed $backingStream;
    private readonly int $contentLength;

    private ?string $initialData;
    private int $bytesLeft;

    private bool $reading = false;

    private bool $doneWithBackingStream = false;

    private readonly DeferredFuture $onClose;
    private bool $closed = false;

    public function __construct($backingStream, int $contentLength, ?string $initialData = null) {
        if (!$backingStream) {
            throw new \InvalidArgumentException("Expected a backing stream");
        }
        if ($contentLength < 0) {
            throw new \InvalidArgumentException("content length cannot be negative: $contentLength");
        }
        $this->backingStream = $backingStream;
        $this->contentLength = $contentLength;

        $this->initialData = $initialData;
        $this->bytesLeft = $contentLength;

        $this->onClose = new DeferredFuture;
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

            if ($this->doneWithBackingStream) {
                return null;
            }

            if ($this->initialData !== null) {
                $chunk = $this->initialData;
                $this->initialData = null;
            }
            else {
                // let zero content length result in read from backing stream
                if ($this->contentLength) {
                    $chunk = $this->backingStream->read($cancellation);
                }
                else {
                    $chunk = null;
                }
            }

            if ($chunk === null) {
                if ($this->bytesLeft) {
                    throw KabomuIOException::createEndOfReadError();
                }
                if ($this->contentLength) {
                    throw new ExpectationViolationException(
                        "expected content length to be zero but found $this->contentLength"
                    );
                }
                $this->doneWithBackingStream = true;
            }
            else {
                $chunkLen = \strlen($chunk);
                if ($chunkLen <= $this->bytesLeft) {
                    $this->bytesLeft -= $chunkLen;
                }
                else {
                    $outstanding = \substr($chunk, $this->bytesLeft - $chunkLen);
                    $this->backingStream->unread($outstanding);
                    $chunk = \substr($chunk, 0, $this->bytesLeft);
                    $this->bytesLeft = 0;
                }
                if (!$this->bytesLeft) {
                    $this->doneWithBackingStream = true;
                }
            }

            return $chunk;
        }
        finally {
            $this->reading = false;
        }
    }

    public function isReadable(): bool {
        return $this->closed || $this->bytesLeft;
    }

    /**
     * Closes the resource, marking it as unusable.
     * Whether pending operations are aborted or not is implementation dependent.
     */
    public function close(): void {
        if (!$this->closed) {
            $this->closed = true;
            $this->onClose->complete();
        }
    }

    /**
     * Returns whether this resource has been closed.
     *
     * @return bool `true` if closed, otherwise `false`.
     */
    public function isClosed(): bool {
        return $this->closed;
    }

    /**
     * Registers a callback that is invoked when this resource is closed.
     *
     * @param \Closure():void $onClose
     */
    public function onClose(\Closure $onClose): void {
        $this->onClose->getFuture()->finally($onClose);
    }
}