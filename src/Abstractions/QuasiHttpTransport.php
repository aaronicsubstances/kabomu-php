<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Abstractions;

/**
 * Represents commonality of functions provided by TCP or IPC mechanisms
 * at both server and client ends.
 */
interface QuasiHttpTransport {

    /**
     * Gets the readable stream associated with a connection
     * for reading a request or response from the connection.
     * @param QuasiHttpConnection $connection connection with readable stream
     * @return mixed readable stream
     */
    function getReadableStream(QuasiHttpConnection $connection);

    /**
     * Gets the writable stream associated with a connection
     * for writing a request or response to the connection.
     * @param QuasiHttpConnection $connection connection with writable stream
     * @return mixed writable stream
     */
    function getWritableStream(QuasiHttpConnection $connection);
}
