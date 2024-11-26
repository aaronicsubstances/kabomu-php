<?php declare(strict_types=1);

namespace AaronicSubstances\Kabomu\Examples\Shared;

use Amp\DeferredCancellation;
use Amp\Socket\Socket;

use AaronicSubstances\Kabomu\Abstractions\DefaultQuasiHttpProcessingOptions;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpConnection;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpProcessingOptions;
use AaronicSubstances\Kabomu\Abstractions\QuasiHttpResponse;
use AaronicSubstances\Kabomu\QuasiHttpUtils;

class SocketConnection implements QuasiHttpConnection {
    private readonly Socket $socket;
    private readonly ?DeferredCancellation $connectCancellation;
    private readonly QuasiHttpProcessingOptions $processingOptions;
    private readonly ?\Closure $timeoutScheduler;

    private ?array $environment = null;

    public function __construct(Socket $socket,
            ?DeferredCancellation $connectCancellation,
            ?QuasiHttpProcessingOptions $processingOptions,
            ?QuasiHttpProcessingOptions $fallbackProcessingOptions) {
        $this->socket = $socket;
        $this->connectCancellation = $connectCancellation;

        $effectiveProcessingOptions = QuasiHttpUtils::mergeProcessingOptions(
            $processingOptions, $fallbackProcessingOptions);
        if (!$effectiveProcessingOptions) {
            $effectiveProcessingOptions = new DefaultQuasiHttpProcessingOptions();
        }
        $this->processingOptions = $effectiveProcessingOptions;

        $this->timeoutScheduler = null;
    }

    public function release(?QuasiHttpResponse $response) {
        $this->connectCancellation?->cancel();
        if ($response?->getBody()) {
            return;
        }
        $this->socket->close();
    }

    public function getStream() {
        return $this->socket;
    }

    function getProcessingOptions(): ?QuasiHttpProcessingOptions {
        return $this->processingOptions;
    }

    function getTimeoutScheduler(): ?\Closure {
        return $this->timeoutScheduler;
    }

    function getEnvironment(): ?array {
        return $this->environment;
    }

    function setEnvironment(?array $environment) {
        $this->environment = $environment;
    }
}