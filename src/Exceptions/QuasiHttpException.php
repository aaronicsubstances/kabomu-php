<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Exceptions;

/**
 * Exception thrown by instances of StandardQuasiHttpClient and
 * StandardQuasiHttpServer classes, which can provide details of
 * error in the form of numeric reason codes.
 * 
 * The reason codes in 0-9 which currently do not have an assigned meaning are reserved for use by this class. 
 * All other numbers including negative values may be used as reason codes.
 */
class QuasiHttpException extends KabomuException {

    /**
     * Indicates general error without much detail to offer aside inspecting 
     * error messages and inner exceptions.
     */
    public const REASON_CODE_GENERAL = 1;

    /**
     * Indicates a timeout in processing.
     */
    public const REASON_CODE_TIMEOUT = 2;

    /**
     * Indicates a problem with encoding/decoding headers.
     */
    public const REASON_CODE_PROTOCOL_VIOLATION = 3;

    /**
     * Indicates a problem with exceeding header or body size limits.
     */
    public const REASON_CODE_MESSAGE_LENGTH_LIMIT_EXCEEDED = 4;
    
    // the following codes are reserved for future use.
    private const reasonCodeReserved5 = 5;
    private const reasonCodeReserved6 = 6;
    private const reasonCodeReserved7 = 7;
    private const reasonCodeReserved8 = 8;
    private const reasonCodeReserved9 = 9;
    private const reasonCodeReserved0 = 0;

    // Redefine the exception so message isn't optional and code is set to a different default.

    /**
     * Creates a new instance with an error message,
     * a reason code and any related previous exception.
     * @param string $message the error message
     * @param int $reasonCode reason code to use. Defaults to 1.
     * @param \Throwable $previous optional cause of this exception
     */
    public function __construct(string $message, int $code = 1, \Throwable $previous = null) {
        switch ($code) {
            case self::reasonCodeReserved5:
            case self::reasonCodeReserved6:
            case self::reasonCodeReserved7:
            case self::reasonCodeReserved8:
            case self::reasonCodeReserved9:
            case self::reasonCodeReserved0:
                throw new \InvalidArgumentException("cannot use reserved reason code: $code");
            default:
                break;
        }

        parent::__construct($message, $code, $previous);
    }
}