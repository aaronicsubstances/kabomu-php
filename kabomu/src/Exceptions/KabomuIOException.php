<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Exceptions;

class KabomuIOException extends KabomuException {

    public static function createEndOfReadError(): self {
        return new KabomuIOException("unexpected end of read");
    }
}