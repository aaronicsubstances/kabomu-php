<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Abstractions;

/**
 * Equivalent of TCP client socket factory that provides
 * {@link \AaronicSubstances\Kabomu\StandardQuasiHttpClient} instances
 * with client connections for sending quasi http requests
 * to servers at remote endpoints.
 */
interface QuasiHttpClientTransport extends QuasiHttpTransport {

    /**
     * Creates a connection to a remote endpoint.
     * @param mixed $remoteEndpoint the target endpoint of the connection
     * allocation request
     * @param ?QuasiHttpProcessingOptions $sendOptions any options given to one of the send*() methods of
     * the {@link \AaronicSubstances\Kabomu\StandardQuasiHttpClient} class
     * @return ?QuasiHttpConnection a connection to remote endpoint
     */
    function allocateConnection($remoteEndpoint, ?QuasiHttpProcessingOptions $sendOptions): ?QuasiHttpConnection;
    
    /**
     * Activates or establishes a connection created with
     * {@link AaronicSubstances\Kabomu\Abstractions\QuasiHttpClientTransport::allocateConnection()}
     * @param QuasiHttpConnection $connection connection to establish before use
     */
    function establishConnection(QuasiHttpConnection $connection);

    /**
     * Releases resources held by a connection of a quasi http transport instance.
     * @param QuasiHttpConnection $connection the connection to release
     * @param ?QuasiHttpResponse $response an optional response which may still need the connection
     * to some extent
     */
    function releaseConnection(QuasiHttpConnection $connection, ?QuasiHttpResponse $response);
}
