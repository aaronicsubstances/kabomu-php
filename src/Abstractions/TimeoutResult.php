<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Abstractions;

/**
 * Represents result of using timeout API as represented by
 * {@link QuasiHttpConnection::getTimeoutScheduler()} method.
 * 
 * @see QuasiHttpConnection::getTimeoutScheduler()
 */
interface TimeoutResult {

    /**
     * Returns true or false depending on whether a timeout occurred
     * or not respectively.
     */
    function isTimeout(): bool;

    /**
     * Gets the value returned by the function argument to the
     * timeout API represented by an instance of the
     * {@link CustomTimeoutScheduler} class.
     */
    function getResponse(): ?QuasiHttpResponse;

    /**
     * Gets any error which was thrown by function argument to the
     * timeout API represented by an instance of the
     * {@link CustomTimeoutScheduler} class.
     */
    function getError(): ?\Throwable;
}