<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Abstractions;

use AaronicSubstances\Kabomu\StandardQuasiHttpServer;

/**
 * Represents a quasi http request processing function used by
 * {@link StandardQuasiHttpServer} instances
 * to generate quasi http responses.
 */
interface QuasiHttpApplication {
    
    /**
     * Processes a quasi http request.
     * @param QuasiHttpRequest $request quasi http request to process
     * @return ?QuasiHttpResponse quasi http response to send back to caller
     */
    function processRequest(QuasiHttpRequest $request): ?QuasiHttpResponse;
}
