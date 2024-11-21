<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Abstractions;

/**
 * Used to configure parameters which affect processing of quasi http requests
 * and responses.
 */
interface QuasiHttpProcessingOptions {

    /**
     * Gets any extra information which can help a transport to locate a communication endpoint.
     */
    function getExtraConnectivityParams(): ?array;

    function setExtraConnectivityParams(?array $value);

    /**
     * Gets the wait time period in milliseconds for a send request to succeed. To indicate
     * forever wait or infinite timeout, use -1 or any negative value.
     */
    function getTimeoutMillis(): ?int;

    function setTimeoutMillis(?int $value);

    /**
     * Gets the value that imposes a maximum size on the headers of requests and
     * responses which will be encountered during sending out requests and
     * receipt of responses.
     * 
     * Note that zero and negative values will be interpreted as unspecified,
     * and in the absence of any overriding options
     * a client-specific default value will be used.
     */
    function getMaxHeadersSize(): ?int;

    function setMaxHeadersSize(?int $value);

    /**
     * Gets the value that imposes a maximum size on response bodies. To indicate absence
     * of a limit, use -1 or any negative value.
     * 
     * Note that zero will be interpreted as unspecified,
     * and in the absence of any overriding options
     * a client-specific default value will be used.
     */
    function getMaxResponseBodySize(): ?int;

    function setMaxResponseBodySize(?int $value);
}
