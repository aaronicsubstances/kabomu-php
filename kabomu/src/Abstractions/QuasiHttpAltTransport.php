<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Abstractions;

use AaronicSubstances\Kabomu\StandardQuasiHttpClient;
use AaronicSubstances\Kabomu\StandardQuasiHttpServer;

/**
 * Represents additional interface that transport property of 
 * {@link StandardQuasiHttpClient} and
 * {@link StandardQuasiHttpServer} classes can
 * implement, in order to override parts of
 * {@link QuasiHttpTransport} functionality.
 */
interface QuasiHttpAltTransport {

    /**
     * Gets a function which can return true to
     * prevent the need to write request headers
     * and body to a connection.
     */
    function getRequestSerializer(): SerializerFunction;

    /**
     * Gets a function which can return true to prevent the
     * need to write response headers and body to a connection.
     */
    function getResponseSerializer(): SerializerFunction;

    /**
     * Gets a function which can return a non-null request object to
     * prevent the need to read request headers from a connection.
     */
    function getRequestDeserializer(): DeserializerFunction;

    /**
     * Gets a function which can return a non-null response object
     * to prevent the need to read response headers from  a
     * connection.
     */
    function getResponseDeserializer(): DeserializerFunction;
}

interface SerializerFunction {
    function serializeEntity(QuasiHttpConnection $connection, QuasiHttpRequest|QuasiHttpResponse $entity): bool;
}

interface DeserializerFunction {
    function deserializeEntity(QuasiHttpConnection $connection): QuasiHttpRequest|QuasiHttpResponse|null;
}