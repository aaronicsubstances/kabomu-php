<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Abstractions;

/**
 * Provides default implementation of the {@link \AaronicSubstances\Kabomu\Abstractions\QuasiHttpProcessingOptions}
 * interface.
 */
class DefaultQuasiHttpProcessingOptions implements QuasiHttpProcessingOptions {
    private ?array $extraConnectivityParams = null;
    private ?int $timeoutMillis = 0;
    private ?int $maxHeadersSize = 0;
    private ?int $maxResponseBodySize = 0;

    public function getExtraConnectivityParams(): ?array {
        return $this->extraConnectivityParams;
    }
    public function setExtraConnectivityParams(?array $extraConnectivityParams) {
        $this->extraConnectivityParams = $extraConnectivityParams;
    }

    public function getTimeoutMillis(): ?int {
        return $this->timeoutMillis;
    }
    public function setTimeoutMillis(?int $timeoutMillis) {
        $this->timeoutMillis = $timeoutMillis;
    }

    public function getMaxHeadersSize(): ?int {
        return $this->maxHeadersSize;
    }
    public function setMaxHeadersSize(?int $maxHeadersSize) {
        $this->maxHeadersSize = $maxHeadersSize;
    }

    public function getMaxResponseBodySize(): ?int {
        return $this->maxResponseBodySize;
    }
    public function setMaxResponseBodySize(?int $maxResponseBodySize) {
        $this->maxResponseBodySize = $maxResponseBodySize;
    }

}
