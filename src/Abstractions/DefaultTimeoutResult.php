<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Abstractions;

/**
 * Provides default implementation of the
 * {@link \AaronicSubstances\Kabomu\Abstractions\TimeoutResult} interface, in which properties are immutable.
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