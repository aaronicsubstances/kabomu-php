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
use AaronicSubstances\Kabomu\Exceptions\ExpectationViolationException;
use AaronicSubstances\Kabomu\Exceptions\KabomuIOException;

class BodyChunkEncodingStreamInternal implements ReadableStream, \IteratorAggregate {
    use ReadableStreamIteratorAggregate;
    use ForbidCloning;
    use ForbidSerialization;

    private readonly mixed $backingStream;
    private readonly int $tagToUse;

    private array $outstanding;

    private bool $reading = false;

    private bool $doneWithBackingStream = FALSE;

    private readonly DeferredFuture $onClose;
    private bool $closed = FALSE;

    public function __construct($backingStream, int $tagToUse) {
        if (!$backingStream) {
            throw new \InvalidArgumentException("Expected a backing stream");
        }

        $this->backingStream = $backingStream;
        $this->tagToUse = $tagToUse;
        $this->onClose = new DeferredFuture;

        $this->outstanding = [];
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

            if (!empty($this->outstanding)) {
                return array_shift($this->outstanding);
            }

            if ($this->doneWithBackingStream) {
                return null;
            }

            // skip over empty but non-null strings
            while (true) {
                $chunk = $this->backingStream->read($cancellation);
                if ($chunk === null || !empty($chunk)) {
                    break;
                }
            }
            if ($chunk === null) {
                $this->outstanding[] = MiscUtilsInternal::serializeInt32BE(0);
                $this->doneWithBackingStream = TRUE;
            }
            else {
                $this->outstanding[] = MiscUtilsInternal::serializeInt32BE(strlen($chunk));
                $this->outstanding[] = $chunk;
            }
            return MiscUtilsInternal::serializeInt32BE($this->tagToUse);
        }
        finally {
            $this->reading = false;
        }
    }

    public function isReadable(): bool {
        return $this->closed || (empty($this->outstanding) && $this->doneWithBackingStream);
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