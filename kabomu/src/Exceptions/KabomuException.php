<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Exceptions;

/**
 * Base exception class for errors encountered in the library.
 */
class KabomuException extends \Exception {

    // Redefine the exception so message isn't optional

    /**
     * Creates a new instance with an error message,
     * a reason code and inner exception.
     * @param string $message the error message
     * @param int $reasonCode reason code to use
     * @param \Throwable $previous optional cause of this exception
     */
    public function __construct(string $message, int $code = 0, \Throwable $previous = null) {
        // some code

        // make sure everything is assigned properly
        parent::__construct($message, $code, $previous);
    }

    // custom string representation of object
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}