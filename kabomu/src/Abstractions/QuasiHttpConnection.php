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
     * Gets an optional function which can be used by
     * {@link StandardQuasiHttpClient} and
     * {@link StandardQuasiHttpServer} instances, to impose
     * timeouts on request processing.
     */
    function getTimeoutScheduler(): ?CustomTimeoutScheduler;

    /**
     * Gets any environment variables that can control decisions
     * during operations by
     * {@link StandardQuasiHttpClient} and
     * {@link StandardQuasiHttpServer} instances.
     */
    function getEnvironment(): ?array;
}
