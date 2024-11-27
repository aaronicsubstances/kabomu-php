<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Abstractions;

/**
 * Equivalent of factory of sockets accepted from a TCP server socket,
 * that provides {@link \AaronicSubstances\Kabomu\StandardQuasiHttpServer} instances
 * with server operations for sending quasi http requests to servers at
 * remote endpoints.
 */
interface QuasiHttpServerTransport extends QuasiHttpTransport {

    /**
     * Releases resources held by a connection of a quasi http transport instance.
     * @param QuasiHttpConnection $connection the connection to release
     */
    function releaseConnection(QuasiHttpConnection $connection);
}
