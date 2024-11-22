<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Abstractions;

use AaronicSubstances\Kabomu\StandardQuasiHttpClient;
use AaronicSubstances\Kabomu\StandardQuasiHttpServer;

/**
 * Represents timeout API for instances of
 * {@link StandardQuasiHttpClient::class} and {@link \AaronicSubstances\Kabomu\StandardQuasiHttpServer}
 * classes to impose timeouts on request processing.
 */
interface CustomTimeoutScheduler {

    /**
     * Applies timeout to request processing.
     * @param \Closure $proc the procedure to run under timeout.
     * Takes QuasiHttpResponse instance as a parameter.
     * @return ?TimeoutResult a result indicating whether a timeout occurred,
     * and gives the return value of the function argument.
     */
    function runUnderTimeout(\Closure $proc): ?TimeoutResult;
}

/**
 * Represents result of using timeout API as represented by
 * {@link CustomTimeoutScheduler} instances.
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

/**
 * Provides default implementation of the
 * {@link TimeoutResult} interface, in which properties are immutable.
 */
class DefaultTimeoutResult implements TimeoutResult {
    private readonly bool $timeout;
    private readonly ?QuasiHttpResponse $response;
    private readonly ?\Throwable $error;
    public function __construct(bool $timeout,
            ?QuasiHttpResponse $response,
            ?\Throwable $error) {
        $this->timeout = $timeout;
        $this->response = $response;
        $this->error = $error;
    }
    public function isTimeout(): bool {
        return $this->timeout;
    }
    public function getResponse(): ?QuasiHttpResponse {
        return $this->response;
    }
    public function getError(): ?\Throwable {
        return $this->error;
    }
}