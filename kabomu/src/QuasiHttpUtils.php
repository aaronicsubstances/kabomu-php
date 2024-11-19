<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu;

use AaronicSubstances\Kabomu\Abstractions\DefaultQuasiHttpProcessingOptions;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpProcessingOptions;

/**
 * Provides helper constants and functions
 * that may be neeeded by clients of Kabomu library.
 */
class QuasiHttpUtils {

    /**
     *  Request environment variable for local server endpoint.
     */
    public const ENV_KEY_LOCAL_PEER_ENDPOINT = "kabomu.local_peer_endpoint";

    /**
     *  Request environment variable for remote client endpoint.
     */
    public const ENV_KEY_REMOTE_PEER_ENDPOINT = "kabomu.remote_peer_endpoint";

    /**
     * Request environment variable for the transport instance from
     * which a request was received.
     */
    public const ENV_KEY_TRANSPORT_INSTANCE = "kabomu.transport";

    /**
     * Request environment variable for the connection from which a
     * request was received.
     */
    public const ENV_KEY_CONNECTION = "kabomu.connection";

    /**
     * Equals HTTP method "CONNECT".
     */
    public const METHOD_CONNECT = "CONNECT";

    /**
     * Equals HTTP method "DELETE".
     */
    public const METHOD_DELETE = "DELETE";

    /**
     * Equals HTTP method "GET".
     */
    public const METHOD_GET = "GET";

    /**
     * Equals HTTP method "HEAD".
     */
    public const METHOD_HEAD = "HEAD";

    /**
     * Equals HTTP method "OPTIONS".
     */
    public const METHOD_OPTIONS = "OPTIONS";

    /**
     * Equals HTTP method "PATCH".
     */
    public const METHOD_PATCH = "PATCH";

    /**
     * Equals HTTP method "POST".
     */
    public const METHOD_POST = "POST";

    /**
     * Equals HTTP method "PUT".
     */
    public const METHOD_PUT = "PUT";

    /**
     * Equals HTTP method "TRACE".
     */
    public const METHOD_TRACE = "TRACE";

    /**
     * 200 OK
     */
    public const STATUS_CODE_OK = 200;

    /**
     * 400 Bad Request
     */
    public const STATUS_CODE_CLIENT_ERROR_BAD_REQUEST = 400;

    /**
     * 401 Unauthorized
     */
    public const STATUS_CODE_CLIENT_ERROR_UNAUTHORIZED = 401;

    /**
     * 403 Forbidden
     */
    public const STATUS_CODE_CLIENT_ERROR_FORBIDDEN = 403;

    /**
     * 404 Not Found
     */
    public const STATUS_CODE_CLIENT_ERROR_NOT_FOUND = 404;

    /**
     * 405 Method Not Allowed
     */
    public const STATUS_CODE_CLIENT_ERROR_METHOD_NOT_ALLOWED = 405;

    /**
     * 413 Payload Too Large
     */
    public const STATUS_CODE_CLIENT_ERROR_PAYLOAD_TOO_LARGE = 413;

    /**
     * 414 URI Too Long
     */
    public const STATUS_CODE_CLIENT_ERROR_URI_TOO_LONG = 414;

    /**
     * 415 Unsupported Media Type
     */
    public const STATUS_CODE_CLIENT_ERROR_UNSUPPORTED_MEDIA_TYPE = 415;

    /**
     * 422 Unprocessable Entity
     */
    public const STATUS_CODE_CLIENT_ERROR_UNPROCESSABLE_ENTITY = 422;

    /**
     * 429 Too Many Requests
     */
    public const STATUS_CODE_CLIENT_ERROR_TOO_MANY_REQUESTS = 429;

    /**
     * 500 Internal Server Error
     */
    public const STATUS_CODE_SERVER_ERROR = 500;

    /**
     * The default value of maximum size of headers in a request or response.
     */
    public const DEFAULT_MAX_HEADERS_SIZE = 8_192;

    /**
     * Merges two sources of processing options together, unless one of
     * them is null, in which case it returns the non-null one.
     * @param ?QuasiHttpProcessingOptions preferred options object whose valid property values will
     * make it to merged result
     * @param ?QuasiHttpProcessingOptions fallback options object whose valid property
     * values will make it to merged result, if corresponding property
     * on preferred argument are invalid.
     * @return ?QuasiHttpProcessingOptions merged options
     */
    public static function mergeProcessingOptions(
            ?QuasiHttpProcessingOptions $preferred,
            ?QuasiHttpProcessingOptions $fallback): ?QuasiHttpProcessingOptions {
        if (!$preferred || !$fallback) {
            if ($preferred) {
                return $preferred;
            }
            return $fallback;
        }
        $mergedOptions = new DefaultQuasiHttpProcessingOptions();
        $mergedOptions->setTimeoutMillis(
            self::determineEffectiveNonZeroIntegerOption(
                $preferred->getTimeoutMillis(),
                $fallback->getTimeoutMillis(),
                0));

        $mergedOptions->setExtraConnectivityParams(
            determineEffectiveOptions(
                $preferred->getExtraConnectivityParams(),
                $fallback->getExtraConnectivityParams()));

        $mergedOptions->setMaxHeadersSize(
            self::determineEffectivePositiveIntegerOption(
                $preferred->getMaxHeadersSize(),
                $fallback->getMaxHeadersSize(),
                0));

        $mergedOptions->setMaxResponseBodySize(
            self::determineEffectiveNonZeroIntegerOption(
                $preferred->getMaxResponseBodySize(),
                $fallback->getMaxResponseBodySize(),
                0));
        return $mergedOptions;
    }

    public static function determineEffectiveNonZeroIntegerOption(?int $preferred,
        ?int $fallback1, int $defaultValue): int
    {
        if ($preferred !== null) {
            $effectiveValue = $preferred;
            if ($effectiveValue !== 0) {
                return $effectiveValue;
            }
        }
        if ($fallback1 !== null) {
            $effectiveValue = $fallback1;
            if ($effectiveValue !== 0) {
                return $effectiveValue;
            }
        }
        return $defaultValue;
    }

    public static function determineEffectivePositiveIntegerOption(?int $preferred,
            ?int $fallback1, int $defaultValue): int {
        if ($preferred !== null) {
            $effectiveValue = $preferred;
            if ($effectiveValue > 0) {
                return $effectiveValue;
            }
        }
        if ($fallback1 !== null) {
            $effectiveValue = $fallback1;
            if ($effectiveValue > 0) {
                return $effectiveValue;
            }
        }
        return $defaultValue;
    }

    public static function determineEffectiveOptions(?array $preferred, ?array $fallback): array {
        $dest = array();
        // since we want preferred options to overwrite fallback options,
        // set fallback options first.
        if ($fallback) {
            foreach ($fallback as $k => $v) {
                $dest[$k] = $v;
            }
        }
        if ($preferred) {
            foreach ($$prefered as $k => $v) {
                $dest[$k] = $v;
            }
        }
        return $dest;
    }
}