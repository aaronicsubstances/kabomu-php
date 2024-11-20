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

class BodyChunkDecodingStreamInternal implements ReadableStream, \IteratorAggregate {
    use ReadableStreamIteratorAggregate;
    use ForbidCloning;
    use ForbidSerialization;

    private readonly mixed $backingStream;
    private readonly int $expectedTag;
    private readonly ?int $tagToIgnore;
    private ?array $initialData;

    private ?array $onDataPushes;

    private bool $reading = false;

    private bool $doneWithBackingStream = FALSE;
    
    private array $chunks;
    private bool $isDecodingHeader = true;
    private int $outstandingDataLength = 0;
    private bool $lastTagSeenIsExpected = true;

    private readonly DeferredFuture $onClose;
    private bool $closed = FALSE;

    public function __construct($backingStream, int $expectedTag, ?int $tagToIgnore = null, ?array $initialData = null) {
        if (!$backingStream) {
            throw new \InvalidArgumentException("Expected a backing stream");
        }

        $this->backingStream = $backingStream;
        $this->expectedTag = $expectedTag;
        $this->tagToIgnore = $tagToIgnore;
        $this->initialData = $initialData;
        $this->onClose = new DeferredFuture;

        $this->chunks = [];
        $this->onDataPushes = [];
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

            while (true) {
                if (!empty($this->onDataPushes)) {
                    return array_shift($this->onDataPushes);
                }
                
                if ($this->doneWithBackingStream) {
                    return null;
                }
                
                if (!empty($this->initialData)) {
                    $chunk = array_shift($this->initialData);
                }
                else {
                    $chunk = $this->backingStream->read($cancellation);
                    if ($chunk === null) {
                        throw KabomuIOException::createEndOfReadError();
                    }
                }

                $this->onData($chunk);
            }
        }
        finally {
            $this->reading = false;
        }
    }

    private function onData($chunk) {
        if (!$this->isDecodingHeader) {
            $chunkLen = strlen($chunk);
            $chunkLengthToUse = min($this->outstandingDataLength, $chunkLen);
            if ($chunkLengthToUse > 0) {
                if ($this->lastTagSeenIsExpected) {
                    $nextChunk = substr($chunk, 0, $chunkLengthToUse);
                    $this->onDataPushes[] = $nextChunk;
                }
                $this->outstandingDataLength -= $chunkLengthToUse;
            }
            if ($chunkLengthToUse < $chunkLen) {
                $carryOverChunk = substr($chunk, $chunkLengthToUse);
                $this->chunks[] = $carryOverChunk;
                $this->isDecodingHeader = true;
                // proceed to loop
            }
            else {
                if (!$this->outstandingDataLength) {
                    // chunk exactly fulfilled outstanding
                    // data length.
                    $this->isDecodingHeader = true;
                    // return or proceed to loop,
                    // it doesn't matter, as chunks should
                    // be empty.
                    if (!empty($this->chunks)) {
                        throw new ExpectationViolationException(
                            "expected chunks to be empty at this point");
                    }
                }
                else {
                    // need to read more chunks to fulfil
                    // chunk data length.
                }
                return;
            }
        }
        else {
            $this->chunks[] = $chunk;
        }
        while (true) {
            $tagAndLen = [0, 0];
            $concatenated = $this->tryDecodeTagAndLength($tagAndLen);
            if (!$concatenated) {
                // need to read more chunks to fulfil
                // chunk header length.
                break;
            }
            $this->chunks = []; // clear
            $decodedTag = $tagAndLen[0];
            if ($this->lastTagSeenIsExpected && $decodedTag === $this->tagToIgnore) {
                // ok.
            }
            else if ($decodedTag !== $this->expectedTag) {
                throw new KabomuIOException("unexpected tag: expected " .
                    "$this->expectedTag but found $decodedTag");
            }
            $this->lastTagSeenIsExpected = $decodedTag === $this->expectedTag;
            $this->outstandingDataLength = $tagAndLen[1];
            $concatenatedLength = strlen($concatenated);
            $concatenatedLengthUsed = 8;
            if ($this->lastTagSeenIsExpected && !$this->outstandingDataLength) {
                // done.
                if ($concatenatedLengthUsed < $concatenatedLength) {
                    $unshift = substr($concatenated, $concatenatedLengthUsed);
                    $this->backingStream->unread($unshift);
                }
                $this->doneWithBackingStream = true;
                return;
            }
            $nextChunkLength = min($this->outstandingDataLength,
                $concatenatedLength - $concatenatedLengthUsed);
            if ($nextChunkLength) {
                if ($this->lastTagSeenIsExpected) {
                    $nextChunk = substr($concatenated,
                        $concatenatedLengthUsed, $nextChunkLength);
                    $this->onDataPushes[] = $nextChunk;
                }
                $this->outstandingDataLength -= $nextChunkLength;
                $concatenatedLengthUsed += $nextChunkLength;
            }
            if ($concatenatedLengthUsed < $concatenatedLength) {
                // can't read more chunks yet, because there are
                // more stuff inside concatenated
                $carryOverChunk = substr($concatenated, $concatenatedLengthUsed);
                $this->chunks[] = $carryOverChunk;
            }
            else {
                if ($this->outstandingDataLength) {
                    // need to read more chunks to fulfil
                    // chunk data length.
                    $this->isDecodingHeader = false;
                }
                else {
                    // chunk exactly fulfilled outstanding
                    // data length.
                    // So start decoding header again.
                }
                // in any case need to read more chunks.
                break;
            }
        }
    }

    private function tryDecodeTagAndLength(array &$result) {
        $totalLength = array_reduce($this->chunks, function($acc, $chunk) {
            return $acc + strlen($chunk);
        }, 0);
        if ($totalLength < 8) {
            return null;
        }
        $decodingBuffer = implode($this->chunks);
        $tag = MiscUtilsInternal::deserializeInt32BE($decodingBuffer, 0);
        if ($tag <= 0) {
            throw new KabomuIOException("invalid tag: $tag");
        }
        $length = MiscUtilsInternal::deserializeInt32BE($decodingBuffer, 4);
        if ($length < 0) {
            throw new KabomuIOException("invalid tag value length: $length");
        }
        $result[0] = $tag;
        $result[1] = $length;
        return $decodingBuffer;
    }

    public function isReadable(): bool {
        return $this->closed || (empty($this->onDataPushes) && $this->doneWithBackingStream);
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