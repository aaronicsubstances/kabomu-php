<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Abstractions;

/**
 * Represents the equivalent of an HTTP response entity: response status line,
 * response headers, and response body.
 */
interface QuasiHttpResponse extends CustomDisposable {
    
    /**
     * Gets the equivalent of HTTP response status code.
     */
    function getStatusCode(): ?int;

    /**
     * Sets the equivalent of HTTP response status code.
     * @param ?int $statusCode
     */
    function setStatusCode(?int $statusCode);

    /**
     * Gets the equivalent of HTTP response headers.
     */
    function getHeaders(): ?array;

    /**
     * Sets the equivalent of HTTP response headers.
     * 
     * Unlike in HTTP, headers are case-sensitive and lower-cased
     * header names are recommended
     *
     * Also setting a Content-Length header
     * here will have no bearing on how to transmit or receive the response body.
     * @param ?string $headers
     */
    function setHeaders(?array $headers);

    /**
     * Gets an HTTP response status text or reason phrase.
     */
    function getHttpStatusMessage(): ?string;

    /**
     * Sets an HTTP response status text or reason phrase.
     * @param ?string $httpStatusMessage
     */
    function setHttpStatusMessage(?string $httpStatusMessage);

    /**
     * Gets an HTTP response version value.
     */
    function getHttpVersion(): ?string;

    /**
     * Sets an HTTP response version value.
     * @param ?string $httpVersion
     */
    function setHttpVersion(?string $httpVersion);

    /**
     * Gets the number of bytes that the instance will supply,
     * or -1 (actually any negative value) to indicate an unknown number of
     * bytes.
     */
    function getContentLength(): ?int;

    /**
     * Sets the number of bytes that the instance will supply,
     * or -1 (actually any negative value) to indicate an unknown number of
     * bytes.
     * @param ?int $contentLength
     */
    function setContentLength(?int $contentLength);

    /**
     * Gets the response body.
     */
    function getBody();

    /**
     * Sets the response body.
     * @param mixed $body
     */
    function setBody($body);

    /**
     * Gets any objects which may be of interest during response processing.
     */
    function getEnvironment(): ?array;

    /**
     * Sets any objects which may be of interest during response processing.
     * @param ?array $environment
     */
    function setEnvironment(?array $environment);

    /**
     * Called to free up resources, e.g. streams.
     */
    function release();
}
