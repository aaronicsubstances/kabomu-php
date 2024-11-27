<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Abstractions;

/**
 * Represents result of using timeout API as represented by
 * {@link \AaronicSubstances\Kabomu\Abstractions\QuasiHttpConnection::getTimeoutScheduler()} method.
 */
interface TimeoutResult {

    /**
     * Returns true or false depending on whether a timeout occurred
     * or not respectively.
     */
    function isTimeout(): bool;

    /**
     * Gets the value returned by the closure argument to the
     * timeout API as represented by
     * {@link \AaronicSubstances\Kabomu\Abstractions\QuasiHttpConnection::getTimeoutScheduler()} method.
     */
    function getResponse(): ?QuasiHttpResponse;

    /**
     * Gets any error which was thrown by closure argument to the
     * timeout API as represented by
     * {@link \AaronicSubstances\Kabomu\Abstractions\QuasiHttpConnection::getTimeoutScheduler()} method.
     */
    function getError(): ?\Throwable;
}