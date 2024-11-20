<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Tlv;

use Amp\Cancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\ReadableStreamIteratorAggregate;

use AaronicSubstances\Kabomu\MiscUtilsInternal;
use AaronicSubstances\Kabomu\Exceptions\KabomuIOException;

class MaxLengthEnforcingStreamInternal implements ReadableStream, \IteratorAggregate {
    use ReadableStreamIteratorAggregate;
    use ForbidCloning;
    use ForbidSerialization;

    private const DEFAULT_MAX_LENGTH = 134_217_728;

    private readonly mixed $backingStream;
    private readonly int $maxLength;

    private int $bytesLeft;

    private bool $reading = false;

    public function __construct($backingStream, int $maxLength = 0) {
        if (!$backingStream) {
            throw new \InvalidArgumentException("Expected a backing stream");
        }
        if (!$maxLength) {
            $maxLength = self::DEFAULT_MAX_LENGTH;
        }
        else if ($maxLength < 0) {
            throw new \InvalidArgumentException("max length cannot be negative: $maxLength");
        }
        $this->backingStream = $backingStream;
        $this->maxLength = $maxLength;
        $this->bytesLeft = $maxLength;
    }

    public function read(?Cancellation $cancellation = null): ?string {
        if ($this->reading) {
            throw new PendingReadError;
        }
        $this->reading = true;
        try {
            $chunk = $this->backingStream->read($cancellation);
            if ($chunk === null) {
                return null;
            }
            $chunkLen = strlen($chunk);
            if ($chunkLen > $this->bytesLeft) {
                throw new KabomuIOException(
                    "stream size exceeds limit of $this->maxLength bytes");
            }
            $this->bytesLeft -= $chunkLen;
            return $chunk;
        }
        finally {
            $this->reading = false;
        }
    }

    public function isReadable(): bool {
        return $this->backingStream->isReadable();
    }

    /**
     * Closes the resource, marking it as unusable.
     * Whether pending operations are aborted or not is implementation dependent.
     */
    public function close(): void {
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