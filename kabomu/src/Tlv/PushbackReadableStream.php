<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Tlv;

use Amp\Cancellation;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\ByteStream\ClosedException;
use Amp\ByteStream\StreamException;
use Amp\ByteStream\ReadableStream;
use Amp\ByteStream\ReadableStreamIteratorAggregate;

class PushbackReadableStream implements ReadableStream, \IteratorAggregate {
    use ReadableStreamIteratorAggregate;
    use ForbidCloning;
    use ForbidSerialization;

    private readonly mixed $backingStream;
    private array $buf;
    private int $pos;

    private bool $reading = false;

    private bool $closed = FALSE;

    public function __construct($backingStream) {
        if (!$backingStream) {
            throw new \InvalidArgumentException("Expected a backing stream");
        }
        $this->backingStream = $backingStream;
        $this->buf = [];
        $this->pos = 0;
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
            if ($this->pos > 0) {
                $chunk = array_pop($this->buf);
                $this->pos -= strlen($chunk);
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
            $this->pos += strlen($data);
        }
        finally {
            $this->reading = false;
        }
    }

    public function isReadable(): bool {
        if (!$this->closed && $this->pos > 0) {
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