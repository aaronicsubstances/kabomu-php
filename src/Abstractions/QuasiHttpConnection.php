<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Abstractions;

use AaronicSubstances\Kabomu\StandardQuasiHttpClient;
use AaronicSubstances\Kabomu\StandardQuasiHttpServer;

/**
 * Represens objects needed by
 * {@link QuasiHttpTransport} instances for reading or writing
 * data.
 */
interface QuasiHttpConnection {

    /**
     * Gets the effective processing options that will be used to
     * limit sizes of headers and response bodies, and configure any
     * other operations by {@link StandardQuasiHttpClient} and
     * {@link StandardQuasiHttpServer} instances.
     */
    function getProcessingOptions(): ?QuasiHttpProcessingOptions;

    /**
     * Gets an optional {@link \Closure} instance which can be used by
     * {@link StandardQuasiHttpClient} and
     * {@link StandardQuasiHttpServer} instances, to impose
     * timeouts on request processing.
     * 
     * It must return an instance of {@link TimeoutResult} which indicates
     * whether a timeout or an error occurred, or which gives the
     * return value of calling its only argument, which is another closure.
     * 
     * The closure takes as its only argument another closure to run under timeout, which
     * has no parameters and returns an instance of {@link QuasiHttpResponse} class.
     */
    function getTimeoutScheduler(): ?\Closure;

    /**
     * Gets any environment variables that can control decisions
     * during operations by
     * {@link StandardQuasiHttpClient} and
     * {@link StandardQuasiHttpServer} instances.
     */
    function getEnvironment(): ?array;
}
