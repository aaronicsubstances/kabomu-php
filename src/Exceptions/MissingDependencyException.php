<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Exceptions;

/**
 * Exception that is thrown by clients to indicate that a required dependency
 * (e.g. a property of the client) has not been set up properly for use
 * (e.g. the property is null).
 */
class MissingDependencyException extends KabomuException {
}