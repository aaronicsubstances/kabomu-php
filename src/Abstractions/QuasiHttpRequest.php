<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Abstractions;

/**
 * Represents the equivalent of an HTTP request entity: request line,
 * request headers, and request body.
 */
interface QuasiHttpRequest extends CustomDisposable {

    /**
     * Gets the equivalent of request target component of HTTP request line.
     */
    function getTarget(): ?string;

    /**
     * Sets the equivalent of request target component of HTTP request line.
     * @param ?string $target
     */
    function setTarget(?string $target);

    /**
     * Gets the equivalent of HTTP request headers as an array,
     * in which each key is a string,
     * and each value is array of strings.
     */
    function getHeaders(): ?array;

    /**
     * Sets the equivalent of HTTP request headers.
     * 
     * Unlike in HTTP, headers are case-sensitive and lower-cased
     * header names are recommended.
     * 
     * Also setting a Content-Length header
     * here will have no bearing on how to transmit or receive the request body.
     */
    function setHeaders(?array $headers);

    /**
     * Gets an HTTP method value.
     */
    function getHttpMethod(): ?string;

    /**
     * Sets an HTTP method value.
     * @param ?string $httpMethod
     */
    function setHttpMethod(?string $httpMethod);

    /**
     * Gets an HTTP request version value.
     */
    function getHttpVersion(): ?string;

    /**
     * Sets an HTTP request version value.
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
     * Gets the request body.
     */
    function getBody();

    /**
     * Sets the request body.
     * @param mixed $body
     */
    function setBody($body);

    /**
     * Gets any objects which may be of interest during request processing.
     */
    function getEnvironment(): ?array;

    /**
     * Sets any objects which may be of interest during request processing.
     * @param ?array $environment
     */
    function setEnvironment(?array $environment);

    function release();
}
