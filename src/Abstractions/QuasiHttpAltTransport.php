<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Abstractions;

/**
 * Represents additional interface that transport property of 
 * {@link \AaronicSubstances\Kabomu\StandardQuasiHttpClient} and
 * {@link \AaronicSubstances\Kabomu\StandardQuasiHttpServer} classes can
 * implement, in order to override parts of
 * {@link \AaronicSubstances\Kabomu\Abstractions\QuasiHttpTransport} functionality.
 */
interface QuasiHttpAltTransport {

    /**
     * Returns null or a closure which takes an instance of {@link \AaronicSubstances\Kabomu\Abstractions\QuasiHttpConnection}
     * as its first parameter, and an instance of {@link \AaronicSubstances\Kabomu\Abstractions\QuasiHttpRequest} as its second parameter,
     * and returns a boolean.
     * 
     * The closure then can return true to
     * prevent the default way of writing request headers
     * and body to a connection.
     * 
     * To proceed with the default processing the closure should return false.
     */
    function getRequestSerializer(): ?\Closure ;

    /**
     * Returns null or a closure which takes an instance of {@link \AaronicSubstances\Kabomu\Abstractions\QuasiHttpConnection}
     * as its first parameter, and an instance of {@link \AaronicSubstances\Kabomu\Abstractions\QuasiHttpResponse} as its second parameter,
     * and returns a boolean.
     * 
     * The closure then can return true to
     * prevent the default way of writing response headers
     * and body to a connection.
     * 
     * To proceed with the default processing the closure should return false.
     */
    function getResponseSerializer(): ?\Closure ;

    /**
     * Returns null or a closure which takes an instance of {@link \AaronicSubstances\Kabomu\Abstractions\QuasiHttpConnection}
     * as its only parameter, and returns null or an instance of {@link \AaronicSubstances\Kabomu\Abstractions\QuasiHttpRequest}.
     * 
     * The closure then can return a non-null request object to
     * prevent the default way of reading request headers from a connection.
     * 
     * To proceed with the default processing the closure should return null.
     */
    function getRequestDeserializer(): ?\Closure ;

    /**
     * Returns null or a closure which takes an instance of {@link \AaronicSubstances\Kabomu\Abstractions\QuasiHttpConnection}
     * as its only parameter, and returns null or an instance of {@link \AaronicSubstances\Kabomu\Abstractions\QuasiHttpResponse}.
     * 
     * The closure then can return a non-null response object to
     * prevent the default way of reading response headers from a connection.
     * 
     * To proceed with the default processing the closure should return null.
     */
    function getResponseDeserializer(): ?\Closure ;
}