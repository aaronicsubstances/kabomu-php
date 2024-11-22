<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Exceptions;

/**
 * Represents errors encountered when reading from or writing to byte streams.
 */
class KabomuIOException extends KabomuException {

    /**
     * Creates exception indicating that reading from a stream has
     * unexpectedly ended.
     */
    public static function createEndOfReadError(): self {
        return new KabomuIOException("unexpected end of read");
    }
}