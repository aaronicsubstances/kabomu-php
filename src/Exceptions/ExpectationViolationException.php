<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Exceptions;

/**
 * Exception thrown to indicate that the caller of a method or function didn't find the output or outcome
 * satisfactory. E.g. the return value from a function is invalid; the function took too long to complete.
 */
class ExpectationViolationException extends KabomuException {
}