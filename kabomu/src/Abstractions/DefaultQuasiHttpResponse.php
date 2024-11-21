<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Abstractions;

/**
 * Provides default implementation of {@link QuasiHttpResponse}
 * interface.
 */
class DefaultQuasiHttpResponse implements QuasiHttpResponse {
    private ?int $statusCode = 0;
    private ?array $headers = null;
    private ?string $httpStatusMessage = null;
    private ?string $httpVersion = null;
    private ?int $contentLength = 0;
    private $body = null;
    private ?array $environment = null;
    private ?\Closure $disposer = null;

    public function getStatusCode(): ?int {
        return $statusCode;
    }
    public function setStatusCode(?int $statusCode) {
        $this->statusCode = $statusCode;
    }

    public function getHeaders(): ?array {
        return $this->headers;
    }
    public function setHeaders(?array $headers) {
        $this->headers = $headers;
    }

    public function getHttpStatusMessage(): ?string {
        return $this->httpStatusMessage;
    }
    public function setHttpStatusMessage(?string $httpStatusMessage) {
        $this->httpStatusMessage = $httpStatusMessage;
    }

    public function getHttpVersion(): ?string {
        return $httpVersion;
    }
    public function setHttpVersion(?string $httpVersion) {
        $this->httpVersion = $httpVersion;
    }

    public function getContentLength(): ?int {
        return $this->contentLength;
    }
    public function setContentLength(?int $contentLength) {
        $this->contentLength = $contentLength;
    }

    public function getBody() {
        return $this->body;
    }
    public function setBody($body) {
        $this->body = $body;
    }

    public function getEnvironment(): ?array {
        return $this->environment;
    }
    public function setEnvironment(?array $environment) {
        $this->environment = $environment;
    }

    public function getDisposer(): ?\Closure {
        return $this->disposer;
    }
    public function setDisposer(?\Closure $disposer) {
        $this->disposer = $disposer;
    }

    public function release() {
        $disposer = $this->disposer;
        if ($disposer) {
            $disposer();
        }
    }
}
