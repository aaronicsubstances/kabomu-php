<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Tlv;

use Amp\Cancellation;
use Amp\DeferredFuture;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\ByteStream\ClosedException;
use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\ReadableStreamIteratorAggregate;

use AaronicSubstances\Kabomu\MiscUtilsInternal;
use AaronicSubstances\Kabomu\Abstractions\PushbackReadableStream;
use AaronicSubstances\Kabomu\Exceptions\ExpectationViolationException;
use AaronicSubstances\Kabomu\Exceptions\KabomuIOException;

class ContentLengthEnforcingStreamInternal implements ReadableStream, \IteratorAggregate {
    use ReadableStreamIteratorAggregate;
    use ForbidCloning;
    use ForbidSerialization;

    private readonly mixed $backingStream;
    private readonly int $contentLength;

    private ?array $initialData;
    private int $bytesLeft;

    private bool $reading = false;

    private bool $doneReading = FALSE;

    private readonly DeferredFuture $onClose;
    private bool $closed = FALSE;

    public function __construct($backingStream, int $contentLength, ?array $initialData = null) {
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
            if ($this->doneReading) {
                return null;
            }
            if (!empty($this->initialData)) {
                $chunk = array_shift($this->initialData);
            }
            else {
                // let zero content length result in read from backing stream
                $chunk = $this->backingStream->read($cancellation);
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
                $this->doneReading = TRUE;
            }
            else {
                $chunkLen = strlen($chunk);
                if ($chunkLen <= $this->bytesLeft) {
                    $this->bytesLeft -= $chunkLen;
                }
                else {
                    if ($this->contentLength) {
                        $outstanding = substr($chunk, $this->bytesLeft - $chunkLen);
                        $this->backingStream->unread($outstanding);
                        $chunk = substr($chunk, 0, $this->bytesLeft);
                    }
                    else {
                        $this->backingStream->unread($chunk);
                        $chunk = null;
                    }
                    $this->bytesLeft = 0;
                }
                if (!$this->bytesLeft) {
                    $this->doneReading = TRUE;
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
            $this->closed = TRUE;
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